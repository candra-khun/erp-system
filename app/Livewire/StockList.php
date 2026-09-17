<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithWarehouseAccess;
use App\Models\Stock;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.erp')]
class StockList extends Component
{
    use InteractsWithWarehouseAccess, WithPagination;

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
        // Reset filter gudang bila di luar scope akses user (via query string)
        $warehouseFilter = $this->clampWarehouseFilter($this->warehouseFilter);
        $this->warehouseFilter = $warehouseFilter;

        $warehouses = $this->accessibleWarehouseOptions();

        $stocks = Stock::query()
            ->with(['product', 'warehouse'])
            ->when($warehouseFilter !== '', fn ($q) => $q->where('warehouse_id', (int) $warehouseFilter))
            ->when($this->accessibleWarehouseIds() !== null, fn ($q) => $q->whereIn('warehouse_id', $this->accessibleWarehouseIds()))
            ->when($this->search, fn ($q) => $q->whereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$this->search}%")))
            ->latest()
            ->paginate(10);

        return view('livewire.stock-list', compact('stocks', 'warehouses'));
    }
}
