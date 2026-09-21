<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\ShiftScheduleSource;
use App\Livewire\Concerns\InteractsWithWarehouseAccess;
use App\Models\Employee;
use App\Models\EmployeeShiftSchedule;
use App\Models\WorkShift;
use App\Support\WarehouseAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

#[Layout('components.layouts.erp', ['title' => 'Penugasan Shift'])]
class ShiftScheduleList extends Component
{
    use InteractsWithWarehouseAccess, WithPagination;

    public string $search = '';

    public ?int $warehouseId = null;

    public bool $showModal = false;

    public bool $isEdit = false;

    public ?int $scheduleId = null;

    public ?int $employeeId = null;

    public ?int $workShiftId = null;

    public string $effectiveDate = '';

    public ?string $endDate = null;

    public bool $recurring = false;

    public string $errorMessage = '';

    protected function rules(): array
    {
        return [
            'employeeId' => 'required|integer',
            'workShiftId' => 'required|integer',
            'effectiveDate' => 'required|date',
            'endDate' => 'nullable|date|after_or_equal:effectiveDate',
            'recurring' => 'boolean',
        ];
    }

    public function mount(): void
    {
        $this->effectiveDate = now()->toDateString();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<EmployeeShiftSchedule>
     */
    #[Computed]
    public function schedules()
    {
        return EmployeeShiftSchedule::with(['employee', 'workShift'])
            ->when($this->search !== '', function ($q): void {
                $q->whereHas('employee', fn ($employee) => $employee->where('full_name', 'like', '%'.$this->search.'%')
                    ->orWhere('employee_number', 'like', '%'.$this->search.'%'));
            })
            ->accessibleWarehouse($this->accessibleWarehouseIds())
            ->orderByDesc('effective_date')
            ->paginate(20);
    }

    /**
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
     * @return Collection<int, WorkShift>
     */
    #[Computed]
    public function workShifts()
    {
        return WorkShift::where('is_active', true)->orderBy('start_time')->get();
    }

    public function createSchedule(): void
    {
        $this->resetForm();
        $this->isEdit = false;
        $this->showModal = true;
    }

    public function editSchedule(int $id): void
    {
        $schedule = $this->resolveAccessibleSchedule($id);

        $this->scheduleId = $schedule->id;
        $this->employeeId = $schedule->employee_id;
        $this->workShiftId = $schedule->work_shift_id;
        $this->effectiveDate = $schedule->effective_date?->format('Y-m-d') ?? now()->toDateString();
        $this->endDate = $schedule->end_date?->format('Y-m-d');
        $this->recurring = $schedule->source === ShiftScheduleSource::Recurring;
        $this->isEdit = true;
        $this->showModal = true;
        $this->errorMessage = '';
    }

    public function saveSchedule(): void
    {
        $validated = $this->validate();

        $employee = $this->resolveAccessibleEmployee((int) $validated['employeeId']);

        try {
            EmployeeShiftSchedule::updateOrCreate(
                ['id' => $this->scheduleId],
                [
                    'employee_id' => $employee->id,
                    'work_shift_id' => (int) $validated['workShiftId'],
                    'effective_date' => $validated['effectiveDate'],
                    'end_date' => $validated['endDate'] ?: null,
                    'source' => $validated['recurring'] ? ShiftScheduleSource::Recurring : ShiftScheduleSource::Manual,
                ],
            );

            $this->showModal = false;
            $this->resetForm();
            session()->flash('success', 'Penugasan shift berhasil disimpan.');
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function deleteSchedule(int $id): void
    {
        $schedule = $this->resolveAccessibleSchedule($id);
        $schedule->delete();
        session()->flash('success', 'Penugasan shift dihapus.');
    }

    private function resolveAccessibleSchedule(int $id): EmployeeShiftSchedule
    {
        $schedule = EmployeeShiftSchedule::findOrFail($id);
        $warehouseId = $schedule->employee?->warehouse_id;

        if ($warehouseId !== null && ! WarehouseAccess::canAccess(auth()->user(), (int) $warehouseId)) {
            abort(403, 'Anda tidak memiliki akses ke data cabang ini.');
        }

        return $schedule;
    }

    private function resolveAccessibleEmployee(int $id): Employee
    {
        $employee = Employee::findOrFail($id);
        $warehouseId = $employee->warehouse_id;

        if ($warehouseId !== null && ! WarehouseAccess::canAccess(auth()->user(), (int) $warehouseId)) {
            abort(403, 'Anda tidak memiliki akses ke data karyawan dari cabang ini.');
        }

        return $employee;
    }

    private function resetForm(): void
    {
        $this->scheduleId = null;
        $this->employeeId = null;
        $this->workShiftId = null;
        $this->effectiveDate = now()->toDateString();
        $this->endDate = null;
        $this->recurring = false;
        $this->errorMessage = '';
        $this->resetErrorBag();
    }

    public function render(): View
    {
        return view('livewire.shift-schedule-list', [
            'schedules' => $this->schedules,
            'employees' => $this->employees,
            'workShifts' => $this->workShifts,
        ]);
    }
}
