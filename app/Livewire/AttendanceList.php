<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use App\Livewire\Concerns\InteractsWithWarehouseAccess;
use App\Models\Attendance;
use App\Models\Employee;
use App\Services\AttendanceService;
use App\Support\WarehouseAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[Layout('components.layouts.erp', ['title' => 'Absensi Karyawan'])]
class AttendanceList extends Component
{
    use InteractsWithWarehouseAccess, WithPagination;

    public string $search = '';

    public ?int $warehouseId = null;

    public string $filterDate = '';

    /** "daily" (per tanggal) atau "monthly" (rekap bulanan) */
    public string $viewMode = 'daily';

    public ?int $filterMonth = null;

    public ?int $filterYear = null;

    public bool $showClockModal = false;

    public ?int $clockEmployeeId = null;

    public string $clockDate = '';

    public string $clockInTime = '';

    public string $clockOutTime = '';

    public string $clockMethod = 'manual';

    public string $clockNote = '';

    public string $errorMessage = '';

    protected function rules(): array
    {
        return [
            'clockEmployeeId' => 'required|integer',
            'clockDate' => 'required|date',
            'clockInTime' => 'nullable|date_format:H:i',
            'clockOutTime' => 'nullable|date_format:H:i',
            'clockMethod' => 'required|in:manual,pin,barcode,fingerprint,gps,selfie,web',
            'clockNote' => 'nullable|string|max:500',
        ];
    }

    public function mount(): void
    {
        $this->filterDate = now()->toDateString();
        $this->filterMonth = (int) now()->format('m');
        $this->filterYear = (int) now()->format('Y');
        $this->clockDate = now()->toDateString();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingViewMode(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<Attendance>
     */
    #[Computed]
    public function attendances()
    {
        return Attendance::with(['employee', 'workShift'])
            ->accessibleWarehouse($this->accessibleWarehouseIds())
            ->when($this->filterDate !== '' && $this->viewMode === 'daily', fn ($q) => $q->where('attendance_date', $this->filterDate))
            ->when($this->viewMode === 'monthly', function ($q): void {
                $q->whereYear('attendance_date', $this->filterYear)
                    ->whereMonth('attendance_date', $this->filterMonth);
            })
            ->when($this->search !== '', function ($q): void {
                $q->whereHas('employee', fn ($employee) => $employee->where('full_name', 'like', '%'.$this->search.'%')
                    ->orWhere('employee_number', 'like', '%'.$this->search.'%'));
            })
            ->orderByDesc('attendance_date')
            ->orderByDesc('clock_in')
            ->paginate(20);
    }

    /**
     * Karyawan aktif untuk dropdown clock in/out manual.
     *
     * @return Collection<int, Employee>
     */
    #[Computed]
    public function employees()
    {
        return Employee::where('is_active', true)
            ->whereNull('resign_date')
            ->accessibleWarehouse($this->accessibleWarehouseIds())
            ->orderBy('full_name')
            ->limit(200)
            ->get();
    }

    /**
     * Rekap bulanan per karyawan untuk mode "monthly".
     *
     * @return array<int, array{employee: Employee, present: int, late: int, absent: int, on_leave: int, holiday: int, late_minutes: int, overtime_hours: float}>
     */
    #[Computed]
    public function monthlyRecap()
    {
        $service = app(AttendanceService::class);
        $start = sprintf('%04d-%02d-01', $this->filterYear, $this->filterMonth);
        $end = sprintf('%04d-%02d-%02d', $this->filterYear, $this->filterMonth, (int) date('t', (int) strtotime($start)));

        return Employee::where('is_active', true)
            ->whereNull('resign_date')
            ->accessibleWarehouse($this->accessibleWarehouseIds())
            ->when($this->search !== '', fn ($q) => $q->where('full_name', 'like', '%'.$this->search.'%')
                ->orWhere('employee_number', 'like', '%'.$this->search.'%'))
            ->orderBy('full_name')
            ->paginate(20)
            ->getCollection()
            ->map(fn (Employee $employee) => $service->recap($employee, $start, $end))
            ->all();
    }

    public function openClockForm(?int $employeeId = null): void
    {
        $this->clockEmployeeId = $employeeId;
        $this->clockDate = $this->filterDate !== '' ? $this->filterDate : now()->toDateString();
        $this->clockInTime = '';
        $this->clockOutTime = '';
        $this->clockNote = '';
        $this->errorMessage = '';
        $this->showClockModal = true;
    }

    /**
     * Catat absensi manual (admin): clock in, clock out, atau keduanya.
     */
    public function saveClock(): void
    {
        $validated = $this->validate();

        $employee = $this->resolveAccessibleEmployee((int) $validated['clockEmployeeId']);
        $service = app(AttendanceService::class);
        $method = AttendanceMethod::from($validated['clockMethod']);
        $date = $validated['clockDate'];

        try {
            if ($validated['clockInTime'] !== null && $validated['clockInTime'] !== '') {
                $service->clockIn($employee, Carbon::parse($date.' '.$validated['clockInTime']), $method, [
                    'notes' => $validated['clockNote'] ?: null,
                ]);
            }

            if ($validated['clockOutTime'] !== null && $validated['clockOutTime'] !== '') {
                $service->clockOut($employee, Carbon::parse($date.' '.$validated['clockOutTime']), $method);
            }

            $this->showClockModal = false;
            session()->flash('success', 'Absensi '.$employee->full_name.' berhasil dicatat.');
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Generate absensi untuk karyawan yang belum tercatat pada tanggal filter
     * (alpha/libur/izin sesuai jadwal, cuti, dan kalender libur).
     */
    public function generateDaily(): void
    {
        if ($this->filterDate === '') {
            $this->errorMessage = 'Pilih tanggal terlebih dahulu.';

            return;
        }

        try {
            $result = app(AttendanceService::class)->generateDailyAttendance($this->filterDate, $this->accessibleWarehouseIds());
            session()->flash('success', $result['generated'].' karyawan diproses untuk tanggal '.$this->filterDate.'.');
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function deleteAttendance(int $id): void
    {
        $attendance = Attendance::findOrFail($id);

        $warehouseId = $attendance->employee?->warehouse_id;

        if ($warehouseId !== null && ! WarehouseAccess::canAccess(auth()->user(), (int) $warehouseId)) {
            abort(403, 'Anda tidak memiliki akses ke data absensi cabang ini.');
        }

        $attendance->delete();
        session()->flash('success', 'Data absensi dihapus.');
    }

    /**
     * Ambil karyawan dan pastikan user berhak mengakses cabangnya.
     *
     * @throws HttpException 403 bila karyawan milik cabang lain.
     */
    private function resolveAccessibleEmployee(int $id): Employee
    {
        $employee = Employee::findOrFail($id);

        $warehouseId = $employee->warehouse_id;

        if ($warehouseId !== null && ! WarehouseAccess::canAccess(auth()->user(), (int) $warehouseId)) {
            abort(403, 'Anda tidak memiliki akses ke data karyawan dari cabang ini.');
        }

        return $employee;
    }

    public function render(): View
    {
        return view('livewire.attendance-list', [
            'attendances' => $this->attendances,
            'employees' => $this->employees,
            'monthlyRecap' => $this->monthlyRecap,
            'statuses' => AttendanceStatus::cases(),
            'methods' => AttendanceMethod::cases(),
        ]);
    }
}
