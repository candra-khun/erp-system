<?php

namespace App\Livewire;

use App\Models\Supplier;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.erp')]
class SupplierList extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public bool $isEdit = false;

    public ?int $supplierId = null;

    public string $name = '';

    public string $contact_person = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
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
        $supplier = Supplier::findOrFail($id);
        $this->supplierId = $supplier->id;
        $this->name = $supplier->name ?? '';
        $this->contact_person = $supplier->contact_person ?? '';
        $this->phone = $supplier->phone ?? '';
        $this->email = $supplier->email ?? '';
        $this->address = $supplier->address ?? '';
        $this->isEdit = true;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'contact_person' => $this->contact_person,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
        ];

        if ($this->isEdit && $this->supplierId) {
            Supplier::findOrFail($this->supplierId)->update($data);
            Session::flash('message', 'Supplier berhasil diperbarui.');
        } else {
            Supplier::create($data);
            Session::flash('message', 'Supplier berhasil ditambahkan.');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    #[On('delete-supplier')]
    public function delete(int $id): void
    {
        Supplier::findOrFail($id)->delete();
        Session::flash('message', 'Supplier berhasil dihapus.');
    }

    private function resetForm(): void
    {
        $this->supplierId = null;
        $this->name = '';
        $this->contact_person = '';
        $this->phone = '';
        $this->email = '';
        $this->address = '';
        $this->resetValidation();
    }

    public function render()
    {
        $suppliers = Supplier::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('contact_person', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(10);

        return view('livewire.supplier-list', compact('suppliers'));
    }
}
