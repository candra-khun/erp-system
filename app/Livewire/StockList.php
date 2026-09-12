<?php

namespace App\Livewire;

use App\Models\Stock;
use App\Models\Warehouse;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.erp')]
class StockList extends Component
{
    use WithPagination;

    public string $search = '';

    public string $warehouseFilter = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingWarehouseFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $warehouses = Warehouse::orderBy('name')->get();

        $stocks = Stock::query()
            ->with(['product', 'warehouse'])
            ->when($this->warehouseFilter, fn ($q) => $q->where('warehouse_id', $this->warehouseFilter))
            ->when($this->search, fn ($q) => $q->whereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$this->search}%")))
            ->latest()
            ->paginate(10);

        return view('livewire.stock-list', compact('stocks', 'warehouses'));
    }
}
