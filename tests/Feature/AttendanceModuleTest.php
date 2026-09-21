<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\OvertimeRequestStatus;
use App\Livewire\AttendanceList;
use App\Livewire\HolidayList;
use App\Livewire\OvertimeRequestList;
use App\Livewire\WorkShiftList;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkShift;
use App\Services\AttendanceService;
use Database\Seeders\AttendanceModuleSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $hrUser;

    private Employee $employee;

    private WorkShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AttendanceModuleSeeder::class);

        $this->hrUser = User::factory()->create();
        $hrRole = Role::where('name', 'admin_cabang')->first();
        $this->hrUser->roles()->attach($hrRole);

        $warehouse = Warehouse::factory()->create();
        $this->shift = WorkShift::first();

        $this->employee = Employee::factory()->create([
            'warehouse_id' => $warehouse->id,
            'work_shift_id' => $this->shift->id,
        ]);
    }

    public function test_hr_user_can_access_attendance_pages(): void
    {
        foreach (['attendances.index', 'work-shifts.index', 'overtime-requests.index', 'holidays.index', 'employee-leaves.index', 'shift-schedules.index'] as $route) {
            $this->actingAs($this->hrUser)
                ->get(route($route))
                ->assertOk();
        }
    }

    public function test_clock_in_marks_present_when_on_time(): void
    {
        $date = '2026-09-17'; // Kamis
        Carbon::setTestNow(Carbon::parse($date.' 08:00:00'));

        $attendance = app(AttendanceService::class)->clockIn($this->employee, Carbon::parse($date.' 08:05:00'));

        $this->assertSame(AttendanceStatus::Present, $attendance->status);
        $this->assertSame(0, $attendance->late_minutes);

        Carbon::setTestNow();
    }

    public function test_clock_in_marks_late_beyond_tolerance(): void
    {
        $date = '2026-09-17';
        Carbon::setTestNow(Carbon::parse($date.' 08:00:00'));

        // Toleransi 15 menit dari 08:00 -> clock in 08:30 = 15 menit telat
        $attendance = app(AttendanceService::class)->clockIn($this->employee, Carbon::parse($date.' 08:30:00'));

        $this->assertSame(AttendanceStatus::Late, $attendance->status);
        $this->assertSame(15, $attendance->late_minutes);

        Carbon::setTestNow();
    }

    public function test_clock_out_records_overtime_past_shift_end(): void
    {
        $date = '2026-09-17';
        Carbon::setTestNow(Carbon::parse($date.' 08:00:00'));

        $service = app(AttendanceService::class);
        $service->clockIn($this->employee, Carbon::parse($date.' 08:00:00'));
        $attendance = $service->clockOut($this->employee, Carbon::parse($date.' 19:00:00'));

        // Shift berakhir 17:00 -> 2 jam lembur
        $this->assertSame('2.00', (string) $attendance->overtime_hours);

        Carbon::setTestNow();
    }

    public function test_double_clock_in_is_rejected(): void
    {
        $date = '2026-09-17';
        Carbon::setTestNow(Carbon::parse($date.' 08:00:00'));

        $service = app(AttendanceService::class);
        $service->clockIn($this->employee, Carbon::parse($date.' 08:00:00'));

        $this->expectException(\RuntimeException::class);
        $service->clockIn($this->employee, Carbon::parse($date.' 09:00:00'));

        Carbon::setTestNow();
    }

    public function test_holiday_and_weekly_off_marked_as_holiday(): void
    {
        $date = '2026-09-20'; // Minggu — weekly off default

        $result = app(AttendanceService::class)->generateDailyAttendance($date);

        $this->assertSame(1, $result['generated']);
        $this->assertSame(AttendanceStatus::Holiday, $this->employee->attendances()->first()->status);
    }

    public function test_approved_leave_marks_on_leave_status(): void
    {
        $date = '2026-09-17'; // Kamus, hari kerja

        $this->employee->leaves()->create([
            'leave_number' => 'LV-TEST-0001',
            'type' => 'annual',
            'start_date' => $date,
            'end_date' => $date,
            'days' => 1,
            'status' => 'approved',
            'approved_by' => $this->hrUser->id,
        ]);

        $result = app(AttendanceService::class)->generateDailyAttendance($date);

        $this->assertSame(1, $result['generated']);
        $this->assertSame(AttendanceStatus::OnLeave, $this->employee->attendances()->first()->status);
    }

    public function test_absent_when_no_record_on_workday(): void
    {
        $date = '2026-09-17'; // Kamis, hari kerja, tanpa cuti/libur

        $result = app(AttendanceService::class)->generateDailyAttendance($date);

        $this->assertSame(1, $result['generated']);
        $this->assertSame(AttendanceStatus::Absent, $this->employee->attendances()->first()->status);
    }

    public function test_work_shift_crud_via_livewire(): void
    {
        Livewire::actingAs($this->hrUser)
            ->test(WorkShiftList::class)
            ->set('name', 'Shift Testing')
            ->set('start_time', '09:00')
            ->set('end_time', '18:00')
            ->set('late_tolerance_minutes', '10')
            ->set('early_leave_tolerance_minutes', '5')
            ->call('saveShift')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('work_shifts', ['name' => 'Shift Testing']);
    }

    public function test_overtime_request_approval_flow(): void
    {
        Livewire::actingAs($this->hrUser)
            ->test(OvertimeRequestList::class)
            ->set('employeeId', $this->employee->id)
            ->set('overtimeDate', '2026-09-17')
            ->set('startTime', '17:00')
            ->set('endTime', '20:00')
            ->call('saveOvertime')
            ->assertHasNoErrors();

        $overtime = OvertimeRequest::first();
        $this->assertSame(OvertimeRequestStatus::Pending, $overtime->status);
        $this->assertSame('3.00', (string) $overtime->hours);

        Livewire::actingAs($this->hrUser)
            ->test(OvertimeRequestList::class)
            ->call('approveOvertime', $overtime->id)
            ->assertHasNoErrors();

        $this->assertSame(OvertimeRequestStatus::Approved, $overtime->fresh()->status);
    }

    public function test_holiday_crud_via_livewire(): void
    {
        Livewire::actingAs($this->hrUser)
            ->test(HolidayList::class)
            ->set('name', 'Hari Raya Testing')
            ->set('holidayDate', '2026-12-25')
            ->set('type', 'national')
            ->set('isRecurringAnnual', true)
            ->call('saveHoliday')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('holidays', ['name' => 'Hari Raya Testing']);
    }

    public function test_manual_clock_in_out_via_livewire(): void
    {
        Livewire::actingAs($this->hrUser)
            ->test(AttendanceList::class)
            ->set('clockEmployeeId', $this->employee->id)
            ->set('clockDate', '2026-09-17')
            ->set('clockInTime', '08:00')
            ->set('clockOutTime', '17:30')
            ->call('saveClock')
            ->assertHasNoErrors();

        $attendance = Attendance::first();
        $this->assertNotNull($attendance->clock_in);
        $this->assertNotNull($attendance->clock_out);
    }

    public function test_monthly_recap_counts_statuses_correctly(): void
    {
        $service = app(AttendanceService::class);
        $date = '2026-09-17';

        Carbon::setTestNow(Carbon::parse($date.' 08:00:00'));
        $service->clockIn($this->employee, Carbon::parse($date.' 08:00:00'));
        $service->clockOut($this->employee, Carbon::parse($date.' 17:30:00'));
        Carbon::setTestNow();

        $recap = $service->recap($this->employee, '2026-09-01', '2026-09-30');

        $this->assertSame(1, $recap['present']);
        $this->assertSame(0.5, (float) $recap['overtime_hours']);
    }
}
