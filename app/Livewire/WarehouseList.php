<?php

namespace App\Livewire;

use App\Models\Warehouse;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.erp')]
class WarehouseList extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public bool $isEdit = false;

    public ?int $warehouseId = null;

    public string $name = '';

    public string $code = '';

    public string $address = '';

    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:warehouses,code,'.$this->warehouseId,
            'address' => 'nullable|string',
            'is_active' => 'boolean',
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
        $warehouse = Warehouse::findOrFail($id);
        $this->warehouseId = $warehouse->id;
        $this->name = $warehouse->name ?? '';
        $this->code = $warehouse->code ?? '';
        $this->address = $warehouse->address ?? '';
        $this->is_active = (bool) $warehouse->is_active;
        $this->isEdit = true;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'code' => $this->code,
            'address' => $this->address,
            'is_active' => $this->is_active,
        ];

        if ($this->isEdit && $this->warehouseId) {
            Warehouse::findOrFail($this->warehouseId)->update($data);
            Session::flash('message', 'Gudang berhasil diperbarui.');
        } else {
            Warehouse::create($data);
            Session::flash('message', 'Gudang berhasil ditambahkan.');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    #[On('delete-warehouse')]
    public function delete(int $id): void
    {
        Warehouse::findOrFail($id)->delete();
        Session::flash('message', 'Gudang berhasil dihapus.');
    }

    private function resetForm(): void
    {
        $this->warehouseId = null;
        $this->name = '';
        $this->code = '';
        $this->address = '';
        $this->is_active = true;
        $this->resetValidation();
    }

    public function render()
    {
        $warehouses = Warehouse::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('code', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(10);

        return view('livewire.warehouse-list', compact('warehouses'));
    }
}
