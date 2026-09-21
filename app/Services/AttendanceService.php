<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use App\Enums\HolidayType;
use App\Enums\LeaveStatus;
use App\Enums\OvertimeRequestStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\EmployeeShiftSchedule;
use App\Models\Holiday;
use App\Models\OvertimeRequest;
use App\Models\WorkShift;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Service absensi (PRD: HR — Manajemen Kehadiran).
 *
 * Tanggung jawab:
 *  - clock in/out karyawan (manual admin, PIN, barcode, atau device fingerprint/GPS/selfie).
 *  - menentukan status otomatis: hadir / terlambat / alpha / izin-cuti / libur.
 *  - menghitung menit terlambat, pulang cepat, dan jam lembur.
 *  - rekap kehadiran per periode (harian/bulanan) untuk payroll.
 *
 * Catatan: verifikasi fingerprint/GPS/selfie dilakukan di sisi device;
 * hasilnya disimpan ke attendance_validations via recordValidation().
 */
class AttendanceService
{
    /**
     * Catat clock in karyawan untuk tanggal tertentu.
     *
     * @param  array{latitude?: float, longitude?: float, selfie_path?: string, notes?: string}  $meta
     *
     * @throws RuntimeException bila karyawan tidak aktif atau sudah clock in.
     */
    public function clockIn(Employee $employee, Carbon $at, AttendanceMethod $method = AttendanceMethod::Manual, array $meta = []): Attendance
    {
        return DB::transaction(function () use ($employee, $at, $method, $meta): Attendance {
            $this->assertEmployeeActive($employee);

            $date = $at->toDateString();

            $existing = Attendance::where('employee_id', $employee->id)
                ->where('attendance_date', $date)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && $existing->clock_in !== null) {
                throw new RuntimeException('Karyawan '.$employee->full_name.' sudah clock in pada '.$date.'.');
            }

            $shift = $this->resolveShift($employee, $date);
            $status = $this->computeStatus($employee, $date, $shift, $at);
            $lateMinutes = $this->computeLateMinutes($shift, $at);

            /** @var Attendance $attendance */
            $attendance = Attendance::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'attendance_date' => $date,
                ],
                array_merge([
                    'attendance_number' => $existing?->attendance_number ?? $this->generateAttendanceNumber($date),
                    'work_shift_id' => $shift?->id,
                    'clock_in' => $at,
                    'status' => $status,
                    'late_minutes' => $lateMinutes,
                    'check_in_method' => $method,
                    'created_by' => auth()->id(),
                ], $meta),
            );

            return $attendance;
        });
    }

    /**
     * Catat clock out: hitung pulang cepat dan jam lembur.
     *
     * @throws RuntimeException bila belum clock in atau sudah clock out.
     */
    public function clockOut(Employee $employee, Carbon $at, AttendanceMethod $method = AttendanceMethod::Manual): Attendance
    {
        return DB::transaction(function () use ($employee, $at, $method): Attendance {
            $this->assertEmployeeActive($employee);

            $date = $at->toDateString();

            /** @var Attendance|null $attendance */
            $attendance = Attendance::where('employee_id', $employee->id)
                ->where('attendance_date', $date)
                ->lockForUpdate()
                ->first();

            if ($attendance === null || $attendance->clock_in === null) {
                throw new RuntimeException('Karyawan '.$employee->full_name.' belum clock in pada '.$date.'.');
            }

            if ($attendance->clock_out !== null) {
                throw new RuntimeException('Karyawan '.$employee->full_name.' sudah clock out pada '.$date.'.');
            }

            $clockIn = Carbon::parse($attendance->clock_in);
            $shift = $attendance->workShift;

            $attendance->update([
                'clock_out' => $at,
                'check_out_method' => $method,
                'early_out_minutes' => $this->computeEarlyOutMinutes($shift, $at),
                'overtime_hours' => $this->computeOvertimeHours($attendance, $clockIn, $at, $shift),
            ]);

            return $attendance->fresh();
        });
    }

    /**
     * Simpan bukti verifikasi device (fingerprint/GPS/selfie/PIN/barcode).
     *
     * @param  array{device_id?: ?string, latitude?: ?float, longitude?: ?float, location_name?: ?string, metadata?: ?array}  $meta
     */
    public function recordValidation(Attendance $attendance, string $method, string $direction, Carbon $at, array $meta = []): void
    {
        $attendance->validations()->create([
            'method' => $method,
            'device_id' => $meta['device_id'] ?? null,
            'direction' => $direction,
            'validated_at' => $at,
            'latitude' => $meta['latitude'] ?? null,
            'longitude' => $meta['longitude'] ?? null,
            'location_name' => $meta['location_name'] ?? null,
            'metadata' => $meta['metadata'] ?? null,
        ]);
    }

    /**
     * Tandai karyawan yang belum tercatat di tanggal tersebut: alpha untuk
     * hari kerja, libur untuk weekly off/hari libur, izin untuk cuti yang
     * disetujui. Dijalankan oleh admin pada akhir hari / proses closing harian.
     *
     * @param  ?list<int>  $warehouseIds  Batasi ke cabang tertentu (null = semua).
     * @return array{generated: int, skipped: int}
     */
    public function generateDailyAttendance(string $date, ?array $warehouseIds = null): array
    {
        return DB::transaction(function () use ($date, $warehouseIds): array {
            $dateString = Carbon::parse($date)->toDateString();
            $generated = 0;

            $employees = Employee::where('is_active', true)
                ->whereNull('resign_date')
                ->accessibleWarehouse($warehouseIds)
                ->whereDoesntHave('attendances', fn ($a) => $a->where('attendance_date', $dateString))
                ->get();

            foreach ($employees as $employee) {
                if ($this->findHoliday($dateString) !== null || $this->isWeeklyOff($dateString)) {
                    $status = AttendanceStatus::Holiday;
                } elseif ($this->findApprovedLeave($employee, $dateString) !== null) {
                    $status = AttendanceStatus::OnLeave;
                } else {
                    $status = AttendanceStatus::Absent;
                }

                $this->createAbsentRecord($employee, $dateString, $status);
                $generated++;
            }

            return ['generated' => $generated, 'skipped' => 0];
        });
    }

    /**
     * Rekap kehadiran karyawan dalam rentang tanggal (inklusif).
     *
     * @return array{
     *     employee: Employee,
     *     present: int,
     *     late: int,
     *     absent: int,
     *     on_leave: int,
     *     holiday: int,
     *     late_minutes: int,
     *     overtime_hours: float
     * }
     */
    public function recap(Employee $employee, string $startDate, string $endDate): array
    {
        $attendances = Attendance::where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->get();

        return [
            'employee' => $employee,
            'present' => $attendances->where('status', AttendanceStatus::Present)->count(),
            'late' => $attendances->where('status', AttendanceStatus::Late)->count(),
            'absent' => $attendances->where('status', AttendanceStatus::Absent)->count(),
            'on_leave' => $attendances->where('status', AttendanceStatus::OnLeave)->count(),
            'holiday' => $attendances->where('status', AttendanceStatus::Holiday)->count(),
            'late_minutes' => (int) $attendances->sum('late_minutes'),
            'overtime_hours' => (float) $attendances->sum('overtime_hours'),
        ];
    }

    /**
     * Total jam lembur karyawan dalam periode: gabungan lembur dari clock out
     * dan pengajuan lembur yang disetujui.
     */
    public function totalOvertimeHours(Employee $employee, string $startDate, string $endDate): float
    {
        $fromAttendance = (float) Attendance::where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->sum('overtime_hours');

        $fromRequests = (float) OvertimeRequest::where('employee_id', $employee->id)
            ->where('status', OvertimeRequestStatus::Approved)
            ->whereBetween('overtime_date', [$startDate, $endDate])
            ->sum('hours');

        return round($fromAttendance + $fromRequests, 2);
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    private function assertEmployeeActive(Employee $employee): void
    {
        if (! $employee->is_active || $employee->resign_date !== null) {
            throw new RuntimeException('Karyawan '.$employee->full_name.' tidak aktif.');
        }
    }

    private function resolveShift(Employee $employee, string $date): ?WorkShift
    {
        $schedule = EmployeeShiftSchedule::where('employee_id', $employee->id)
            ->where('effective_date', '<=', $date)
            ->where(function ($q) use ($date): void {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $date);
            })
            ->orderByDesc('effective_date')
            ->first();

        if ($schedule !== null) {
            return $schedule->workShift;
        }

        return $employee->workShift;
    }

    private function computeStatus(Employee $employee, string $date, ?WorkShift $shift, Carbon $clockInAt): AttendanceStatus
    {
        $holiday = $this->findHoliday($date);

        if ($holiday !== null || $this->isWeeklyOff($date)) {
            return AttendanceStatus::Holiday;
        }

        if ($this->findApprovedLeave($employee, $date) !== null) {
            return AttendanceStatus::OnLeave;
        }

        if ($shift !== null && $this->computeLateMinutes($shift, $clockInAt) > 0) {
            return AttendanceStatus::Late;
        }

        return AttendanceStatus::Present;
    }

    private function computeLateMinutes(?WorkShift $shift, Carbon $at): int
    {
        if ($shift === null) {
            return 0;
        }

        $deadline = $this->shiftDeadline($shift, $at);

        // Catatan: diffInMinutes() pada versi Carbon yang terpasang bersifat
        // bertanda (argumen - penerima), jadi arah argumen harus dibalik.
        return $at->greaterThan($deadline) ? (int) $deadline->diffInMinutes($at) : 0;
    }

    private function computeEarlyOutMinutes(?WorkShift $shift, Carbon $at): int
    {
        if ($shift === null) {
            return 0;
        }

        $end = $this->rawShiftEnd($shift, $at);
        $earliest = $end->copy()->subMinutes((int) $shift->early_leave_tolerance_minutes);

        return $at->lessThan($earliest) ? (int) $at->diffInMinutes($earliest) : 0;
    }

    /**
     * Jam lembur = jam di luar shift setelah clock out, ditambah pengajuan
     * lembur yang disetujui untuk tanggal tersebut.
     */
    private function computeOvertimeHours(Attendance $attendance, Carbon $clockIn, Carbon $clockOut, ?WorkShift $shift): float
    {
        $hours = 0.0;

        if ($shift !== null) {
            $end = $this->rawShiftEnd($shift, $clockOut);

            if ($clockOut->greaterThan($end)) {
                $hours = round($end->diffInMinutes($clockOut) / 60, 2);
            }
        }

        $approved = OvertimeRequest::where('employee_id', $attendance->employee_id)
            ->where('overtime_date', $attendance->attendance_date)
            ->where('status', OvertimeRequestStatus::Approved)
            ->sum('hours');

        return round($hours + (float) $approved, 2);
    }

    /**
     * Deadline clock in = jam mulai shift + toleransi terlambat, pada tanggal
     * saat absensi terjadi.
     */
    private function shiftDeadline(WorkShift $shift, Carbon $at): Carbon
    {
        return $this->rawShiftStart($shift, $at)
            ->addMinutes((int) $shift->late_tolerance_minutes);
    }

    /**
     * Jam selesai shift murni (tanpa potongan toleransi) pada tanggal tertentu.
     */
    private function rawShiftEnd(WorkShift $shift, Carbon $at): Carbon
    {
        return $this->shiftTime($shift->end_time, $at);
    }

    /**
     * Jam mulai shift murni (tanpa toleransi) pada tanggal tertentu.
     */
    private function rawShiftStart(WorkShift $shift, Carbon $at): Carbon
    {
        return $this->shiftTime($shift->start_time, $at);
    }

    /**
     * Bangun objek Carbon untuk jam shift pada tanggal absensi.
     */
    private function shiftTime(Carbon $time, Carbon $at): Carbon
    {
        return $time->copy()
            ->setDate((int) $at->format('Y'), (int) $at->format('m'), (int) $at->format('d'));
    }

    private function findHoliday(string $date): ?Holiday
    {
        $day = Carbon::parse($date);

        return Holiday::where('holiday_date', $date)
            ->orWhere(function ($q) use ($day): void {
                $q->where('is_recurring_annual', true)
                    ->whereMonth('holiday_date', $day->month)
                    ->whereDay('holiday_date', $day->day);
            })
            ->first();
    }

    /**
     * Weekly off: cek holiday type weekly_off yang cocok dengan hari tanggal.
     */
    private function isWeeklyOff(string $date): bool
    {
        // dayOfWeekIso: 1=Senin ... 7=Minggu — simpan sebagai 0=Minggu.
        $dayOfWeek = Carbon::parse($date)->dayOfWeekIso;
        $dayOfWeek = $dayOfWeek === 7 ? 0 : $dayOfWeek;

        return Holiday::where('type', HolidayType::WeeklyOff)
            ->where('weekly_day_of_week', $dayOfWeek)
            ->exists();
    }

    private function findApprovedLeave(Employee $employee, string $date): ?EmployeeLeave
    {
        return $employee->leaves()
            ->where('status', LeaveStatus::Approved)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
    }

    private function createAbsentRecord(Employee $employee, string $date, AttendanceStatus $status): Attendance
    {
        /** @var Attendance $attendance */
        $attendance = Attendance::firstOrCreate(
            [
                'employee_id' => $employee->id,
                'attendance_date' => $date,
            ],
            [
                'attendance_number' => $this->generateAttendanceNumber($date),
                'status' => $status,
                'created_by' => auth()->id(),
            ],
        );

        return $attendance;
    }

    private function generateAttendanceNumber(string $date): string
    {
        $prefix = 'ATT-'.str_replace('-', '', $date).'-';
        $last = Attendance::where('attendance_number', 'like', $prefix.'%')
            ->orderByDesc('attendance_number')
            ->value('attendance_number');

        $seq = $last ? ((int) substr((string) $last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
