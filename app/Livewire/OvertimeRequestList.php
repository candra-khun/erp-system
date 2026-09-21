<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\OvertimeRequestStatus;
use App\Livewire\Concerns\InteractsWithWarehouseAccess;
use App\Models\Employee;
use App\Models\OvertimeRequest;
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

#[Layout('components.layouts.erp', ['title' => 'Pengajuan Lembur'])]
class OvertimeRequestList extends Component
{
    use InteractsWithWarehouseAccess, WithPagination;

    public string $search = '';

    public ?int $warehouseId = null;

    public bool $showModal = false;

    public bool $isEdit = false;

    public ?int $overtimeId = null;

    public ?int $employeeId = null;

    public string $overtimeDate = '';

    public string $startTime = '';

    public string $endTime = '';

    public string $description = '';

    public string $errorMessage = '';

    protected function rules(): array
    {
        return [
            'employeeId' => 'required|integer',
            'overtimeDate' => 'required|date',
            'startTime' => 'required|date_format:H:i',
            'endTime' => 'required|date_format:H:i|after:startTime',
            'description' => 'nullable|string|max:500',
        ];
    }

    public function mount(): void
    {
        $this->overtimeDate = now()->toDateString();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<OvertimeRequest>
     */
    #[Computed]
    public function overtimes()
    {
        return OvertimeRequest::with(['employee', 'approver'])
            ->accessibleWarehouse($this->accessibleWarehouseIds())
            ->when($this->search !== '', function ($q): void {
                $q->whereHas('employee', fn ($employee) => $employee->where('full_name', 'like', '%'.$this->search.'%')
                    ->orWhere('employee_number', 'like', '%'.$this->search.'%'));
            })
            ->orderByDesc('overtime_date')
            ->orderByDesc('created_at')
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

    public function createOvertime(): void
    {
        $this->resetForm();
        $this->isEdit = false;
        $this->showModal = true;
    }

    public function saveOvertime(): void
    {
        $validated = $this->validate();

        $employee = $this->resolveAccessibleEmployee((int) $validated['employeeId']);

        $hours = $this->calculateHours($validated['startTime'], $validated['endTime']);

        OvertimeRequest::updateOrCreate(
            ['id' => $this->overtimeId],
            [
                'overtime_number' => $this->overtimeId
                    ? OvertimeRequest::find($this->overtimeId)?->overtime_number
                    : $this->generateOvertimeNumber($validated['overtimeDate']),
                'employee_id' => $employee->id,
                'overtime_date' => $validated['overtimeDate'],
                'start_time' => $validated['startTime'].':00',
                'end_time' => $validated['endTime'].':00',
                'hours' => $hours,
                'description' => $validated['description'] ?: null,
                'status' => OvertimeRequestStatus::Pending,
            ],
        );

        $this->showModal = false;
        $this->resetForm();
        session()->flash('success', 'Pengajuan lembur berhasil dibuat, menunggu approval.');
    }

    public function approveOvertime(int $id): void
    {
        $overtime = $this->resolveAccessibleOvertime($id);

        try {
            $overtime->update([
                'status' => OvertimeRequestStatus::Approved,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);

            session()->flash('success', 'Pengajuan lembur disetujui. Jam lembur: '.$overtime->hours.' jam.');
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function rejectOvertime(int $id): void
    {
        $overtime = $this->resolveAccessibleOvertime($id);

        $overtime->update([
            'status' => OvertimeRequestStatus::Rejected,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        session()->flash('success', 'Pengajuan lembur ditolak.');
    }

    public function cancelOvertime(int $id): void
    {
        $overtime = $this->resolveAccessibleOvertime($id);
        $overtime->update(['status' => OvertimeRequestStatus::Cancelled]);
        session()->flash('success', 'Pengajuan lembur dibatalkan.');
    }

    private function calculateHours(string $start, string $end): float
    {
        $start = Carbon::parse($start);
        $end = Carbon::parse($end);

        // Lembur tengah malam: jam selesai lebih awal dari jam mulai = lewat tengah malam.
        if ($end <= $start) {
            $end = $end->addDay();
        }

        return round($start->diffInMinutes($end) / 60, 2);
    }

    private function generateOvertimeNumber(string $date): string
    {
        $prefix = 'OT-'.str_replace('-', '', $date).'-';
        $last = OvertimeRequest::where('overtime_number', 'like', $prefix.'%')
            ->orderByDesc('overtime_number')
            ->value('overtime_number');

        $seq = $last ? ((int) substr((string) $last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function resolveAccessibleOvertime(int $id): OvertimeRequest
    {
        $overtime = OvertimeRequest::findOrFail($id);
        $warehouseId = $overtime->employee?->warehouse_id;

        if ($warehouseId !== null && ! WarehouseAccess::canAccess(auth()->user(), (int) $warehouseId)) {
            abort(403, 'Anda tidak memiliki akses ke data cabang ini.');
        }

        return $overtime;
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
        $this->overtimeId = null;
        $this->employeeId = null;
        $this->overtimeDate = now()->toDateString();
        $this->startTime = '';
        $this->endTime = '';
        $this->description = '';
        $this->errorMessage = '';
        $this->resetErrorBag();
    }

    public function render(): View
    {
        return view('livewire.overtime-request-list', [
            'overtimes' => $this->overtimes,
            'employees' => $this->employees,
        ]);
    }
}
