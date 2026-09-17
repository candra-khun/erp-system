<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithWarehouseAccess;
use App\Models\Employee;
use App\Models\Warehouse;
use App\Support\WarehouseAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[Layout('components.layouts.erp', ['title' => 'Karyawan'])]
class EmployeeList extends Component
{
    use InteractsWithWarehouseAccess, WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public bool $isEdit = false;

    public ?int $employeeId = null;

    public ?int $warehouseId = null;

    public string $employee_number = '';

    public string $full_name = '';

    public string $id_card_number = '';

    public string $npwp = '';

    public ?int $userId = null;

    public string $position = '';

    public string $department = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public ?string $hire_date = null;

    public ?string $bank_account_number = null;

    public ?string $bank_account_name = null;

    public ?string $bank_name = null;

    public string $basic_salary = '';

    public string $payment_type = 'monthly';

    public string $tax_status = 'non_taxable';

    public bool $is_active = true;

    public string $errorMessage = '';

    protected function rules(): array
    {
        return [
            'employee_number' => 'required|string|max:50',
            'full_name' => 'required|string|max:255',
            'id_card_number' => 'nullable|string|max:50',
            'npwp' => 'nullable|string|max:50',
            'warehouseId' => ['nullable', 'integer'],
            'position' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'hire_date' => 'nullable|date',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_account_name' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:100',
            'basic_salary' => 'required|numeric|min:0',
            'payment_type' => 'required|in:monthly,daily,hourly',
            'tax_status' => 'required|in:non_taxable,taxable',
            'is_active' => 'boolean',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<Employee>
     */
    #[Computed]
    public function employees()
    {
        return Employee::with(['warehouse', 'user'])
            ->accessibleWarehouse($this->accessibleWarehouseIds())
            ->when($this->search !== '', fn ($q) => $q->where(function ($q): void {
                $q->where('full_name', 'like', '%'.$this->search.'%')
                    ->orWhere('employee_number', 'like', '%'.$this->search.'%')
                    ->orWhere('position', 'like', '%'.$this->search.'%')
                    ->orWhere('department', 'like', '%'.$this->search.'%');
            }))
            ->orderByDesc('id')
            ->paginate(15);
    }

    /**
     * @return Collection<int, Warehouse>
     */
    #[Computed]
    public function warehouseOptions()
    {
        return $this->accessibleWarehouseOptions();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->isEdit = false;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $employee = $this->resolveAccessibleEmployee($id);

        $this->employeeId = $employee->id;
        $this->employee_number = $employee->employee_number ?? '';
        $this->full_name = $employee->full_name ?? '';
        $this->id_card_number = $employee->id_card_number ?? '';
        $this->npwp = $employee->npwp ?? '';
        $this->warehouseId = $employee->warehouse_id;
        $this->position = $employee->position ?? '';
        $this->department = $employee->department ?? '';
        $this->phone = $employee->phone ?? '';
        $this->email = $employee->email ?? '';
        $this->address = $employee->address ?? '';
        $this->hire_date = $employee->hire_date?->format('Y-m-d');
        $this->bank_account_number = $employee->bank_account_number;
        $this->bank_account_name = $employee->bank_account_name;
        $this->bank_name = $employee->bank_name;
        $this->basic_salary = (string) $employee->basic_salary;
        $this->payment_type = $employee->payment_type;
        $this->tax_status = $employee->tax_status;
        $this->is_active = (bool) $employee->is_active;

        $this->isEdit = true;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate();

        $warehouseId = $this->warehouseId === null ? null : $this->clampWarehouseId($this->warehouseId);

        if ($warehouseId === null && $this->warehouseId !== null) {
            $this->errorMessage = 'Anda tidak memiliki akses ke cabang tersebut.';
            $this->showModal = false;

            return;
        }

        $data = [
            'employee_number' => $validated['employee_number'],
            'full_name' => $validated['full_name'],
            'id_card_number' => $validated['id_card_number'] ?: null,
            'npwp' => $validated['npwp'] ?: null,
            'warehouse_id' => $warehouseId,
            'position' => $validated['position'] ?: null,
            'department' => $validated['department'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'email' => $validated['email'] ?: null,
            'address' => $validated['address'] ?: null,
            'hire_date' => $validated['hire_date'],
            'bank_account_number' => $this->bank_account_number,
            'bank_account_name' => $this->bank_account_name,
            'bank_name' => $this->bank_name,
            'basic_salary' => (float) $validated['basic_salary'],
            'payment_type' => $validated['payment_type'],
            'tax_status' => $validated['tax_status'],
            'is_active' => $validated['is_active'],
        ];

        if ($this->isEdit) {
            $employee = $this->resolveAccessibleEmployee((int) $this->employeeId);
            $employee->update($data);
            session()->flash('success', 'Data karyawan berhasil diperbarui.');
        } else {
            Employee::create($data);
            session()->flash('success', 'Karyawan baru berhasil ditambahkan.');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        $employee = $this->resolveAccessibleEmployee($id);
        $employee->update(['is_active' => ! $employee->is_active]);

        session()->flash('success', 'Status karyawan diperbarui.');
    }

    public function delete(int $id): void
    {
        $employee = $this->resolveAccessibleEmployee($id);

        try {
            $employee->delete();
            session()->flash('success', 'Karyawan berhasil dihapus (soft delete).');
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
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

    private function resetForm(): void
    {
        $this->reset([
            'employeeId', 'employee_number', 'full_name', 'id_card_number', 'npwp',
            'warehouseId', 'position', 'department', 'phone', 'email', 'address',
            'hire_date', 'bank_account_number', 'bank_account_name', 'bank_name',
            'basic_salary', 'payment_type', 'tax_status', 'is_active', 'errorMessage',
        ]);

        $this->is_active = true;
        $this->payment_type = 'monthly';
        $this->tax_status = 'non_taxable';
    }

    public function render()
    {
        return view('livewire.employee-list', ['employees' => $this->employees, 'warehouseOptions' => $this->warehouseOptions]);
    }
}
