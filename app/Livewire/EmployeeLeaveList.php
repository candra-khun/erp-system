<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\LeaveStatus;
use App\Livewire\Concerns\InteractsWithWarehouseAccess;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Support\WarehouseAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.erp', ['title' => 'Pengajuan Izin / Cuti'])]
class EmployeeLeaveList extends Component
{
    use InteractsWithWarehouseAccess, WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public bool $isEdit = false;

    public ?int $leaveId = null;

    public ?int $employeeId = null;

    public string $type = 'annual';

    public string $startDate = '';

    public string $endDate = '';

    public string $notes = '';

    public string $errorMessage = '';

    protected function rules(): array
    {
        return [
            'employeeId' => 'required|integer',
            'type' => 'required|in:annual,sick,unpaid,other',
            'startDate' => 'required|date',
            'endDate' => 'required|date|after_or_equal:startDate',
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function mount(): void
    {
        $this->startDate = now()->toDateString();
        $this->endDate = now()->toDateString();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<EmployeeLeave>
     */
    #[Computed]
    public function leaves()
    {
        return EmployeeLeave::with(['employee', 'approver'])
            ->accessibleWarehouse($this->accessibleWarehouseIds())
            ->when($this->search !== '', function ($q): void {
                $q->whereHas('employee', fn ($employee) => $employee->where('full_name', 'like', '%'.$this->search.'%')
                    ->orWhere('employee_number', 'like', '%'.$this->search.'%'));
            })
            ->orderByDesc('start_date')
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

    public function createLeave(): void
    {
        $this->resetForm();
        $this->isEdit = false;
        $this->showModal = true;
    }

    public function saveLeave(): void
    {
        $validated = $this->validate();

        $employee = $this->resolveAccessibleEmployee((int) $validated['employeeId']);
        $days = $this->calculateDays($validated['startDate'], $validated['endDate']);

        EmployeeLeave::updateOrCreate(
            ['id' => $this->leaveId],
            [
                'leave_number' => $this->leaveId
                    ? EmployeeLeave::find($this->leaveId)?->leave_number
                    : $this->generateLeaveNumber(),
                'employee_id' => $employee->id,
                'type' => $validated['type'],
                'start_date' => $validated['startDate'],
                'end_date' => $validated['endDate'],
                'days' => $days,
                'notes' => $validated['notes'] ?: null,
                'status' => LeaveStatus::Pending,
            ],
        );

        $this->showModal = false;
        $this->resetForm();
        session()->flash('success', 'Pengajuan izin/cuti berhasil dibuat, menunggu approval.');
    }

    public function approveLeave(int $id): void
    {
        $leave = $this->resolveAccessibleLeave($id);

        $leave->update([
            'status' => LeaveStatus::Approved,
            'approved_by' => auth()->id(),
        ]);

        session()->flash('success', 'Pengajuan izin/cuti disetujui.');
    }

    public function rejectLeave(int $id): void
    {
        $leave = $this->resolveAccessibleLeave($id);

        $leave->update([
            'status' => LeaveStatus::Rejected,
            'approved_by' => auth()->id(),
        ]);

        session()->flash('success', 'Pengajuan izin/cuti ditolak.');
    }

    public function cancelLeave(int $id): void
    {
        $leave = $this->resolveAccessibleLeave($id);
        $leave->update(['status' => LeaveStatus::Cancelled]);
        session()->flash('success', 'Pengajuan izin/cuti dibatalkan.');
    }

    private function calculateDays(string $start, string $end): float
    {
        return round(Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1, 2);
    }

    private function generateLeaveNumber(): string
    {
        $prefix = 'LV-'.now()->format('Ymd').'-';
        $last = EmployeeLeave::where('leave_number', 'like', $prefix.'%')
            ->orderByDesc('leave_number')
            ->value('leave_number');

        $seq = $last ? ((int) substr((string) $last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function resolveAccessibleLeave(int $id): EmployeeLeave
    {
        $leave = EmployeeLeave::findOrFail($id);
        $warehouseId = $leave->employee?->warehouse_id;

        if ($warehouseId !== null && ! WarehouseAccess::canAccess(auth()->user(), (int) $warehouseId)) {
            abort(403, 'Anda tidak memiliki akses ke data cabang ini.');
        }

        return $leave;
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
        $this->leaveId = null;
        $this->employeeId = null;
        $this->type = 'annual';
        $this->startDate = now()->toDateString();
        $this->endDate = now()->toDateString();
        $this->notes = '';
        $this->errorMessage = '';
        $this->resetErrorBag();
    }

    public function render(): View
    {
        return view('livewire.employee-leave-list', [
            'leaves' => $this->leaves,
            'employees' => $this->employees,
        ]);
    }
}
