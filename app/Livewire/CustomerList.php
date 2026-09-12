<?php

namespace App\Livewire;

use App\Enums\CustomerType;
use App\Models\Customer;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.erp')]
class CustomerList extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public bool $isEdit = false;

    public ?int $customerId = null;

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public string $customer_type = '';

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'customer_type' => 'required|string',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->isEdit = false;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $customer = Customer::findOrFail($id);
        $this->customerId = $customer->id;
        $this->name = $customer->name ?? '';
        $this->phone = $customer->phone ?? '';
        $this->email = $customer->email ?? '';
        $this->address = $customer->address ?? '';
        $this->customer_type = $customer->type ?? '';
        $this->isEdit = true;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'type' => $this->customer_type,
        ];

        if ($this->isEdit && $this->customerId) {
            Customer::findOrFail($this->customerId)->update($data);
            Session::flash('message', 'Customer berhasil diperbarui.');
        } else {
            Customer::create($data);
            Session::flash('message', 'Customer berhasil ditambahkan.');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    #[On('delete-customer')]
    public function delete(int $id): void
    {
        Customer::findOrFail($id)->delete();
        Session::flash('message', 'Customer berhasil dihapus.');
    }

    private function resetForm(): void
    {
        $this->customerId = null;
        $this->name = '';
        $this->phone = '';
        $this->email = '';
        $this->address = '';
        $this->customer_type = CustomerType::General->value;
        $this->resetValidation();
    }

    public function render()
    {
        $customers = Customer::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(10);

        return view('livewire.customer-list', compact('customers'));
    }
}
