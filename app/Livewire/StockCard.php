<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Livewire\Component;
use Livewire\WithPagination;

class StockCard extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $filterProductId = null;

    public ?int $filterWarehouseId = null;

    public ?string $filterType = null;

    protected $queryString = ['search', 'filterProductId', 'filterWarehouseId', 'filterType'];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $products = Product::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'sku', 'name']);

        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);

        $movements = StockMovement::with(['product', 'warehouse', 'creator'])
            ->when($this->search, function ($query): void {
                $query->whereHas('product', function ($q): void {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhere('sku', 'like', "%{$this->search}%");
                });
            })
            ->when($this->filterProductId, function ($query): void {
                $query->where('product_id', $this->filterProductId);
            })
            ->when($this->filterWarehouseId, function ($query): void {
                $query->where('warehouse_id', $this->filterWarehouseId);
            })
            ->when($this->filterType, function ($query): void {
                $query->where('type', $this->filterType);
            })
            ->latest('movement_date')
            ->paginate(20);

        return view('livewire.stock-card', compact('movements', 'products', 'warehouses'))
            ->layout('components.layouts.erp', ['title' => 'Kartu Stok']);
    }
}
