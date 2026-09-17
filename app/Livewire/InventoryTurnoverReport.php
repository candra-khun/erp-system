<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithWarehouseAccess;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockMovement;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.erp', ['title' => 'Laporan Perputaran Stok'])]
class InventoryTurnoverReport extends Component
{
    /** @use InteractsWithWarehouseAccess<self> */
    use InteractsWithWarehouseAccess;

    public string $startDate = '';

    public string $endDate = '';

    public ?int $filterWarehouseId = null;

    public ?int $filterCategoryId = null;

    public function mount(): void
    {
        $this->startDate = now()->subMonth()->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
    }

    public function render(): View
    {
        $this->filterWarehouseId = $this->clampWarehouseId($this->filterWarehouseId);
        $warehouses = $this->accessibleWarehouseOptions();
        $categories = ProductCategory::orderBy('name')->get(['id', 'name']);

        $productsQuery = Product::with(['category', 'stocks'])
            ->where('is_active', true)
            ->when($this->filterCategoryId, fn ($q) => $q->where('product_category_id', $this->filterCategoryId));

        if ($this->filterWarehouseId) {
            $productsQuery->whereHas('stocks', fn ($q) => $q->where('warehouse_id', $this->filterWarehouseId));
        }

        $products = $productsQuery->get();

        $reportData = $products->map(function (Product $product): array {
            $movements = StockMovement::where('product_id', $product->id)
                ->when($this->filterWarehouseId, fn ($q) => $q->where('warehouse_id', $this->filterWarehouseId))
                ->whereBetween('movement_date', [$this->startDate, $this->endDate])
                ->get();

            $totalIn = $movements->where('type', 'in')->sum('quantity');
            $totalOut = $movements->where('type', 'out')->sum('quantity');

            $currentStock = $this->filterWarehouseId
                ? ($product->stocks->firstWhere('warehouse_id', $this->filterWarehouseId)?->quantity ?? 0)
                : $product->stocks->sum('quantity');

            $openingStock = $currentStock - $totalIn + $totalOut;
            $avgStock = ($openingStock + $currentStock) / 2;
            $turnover = $avgStock > 0 ? round($totalOut / $avgStock, 2) : 0;

            return [
                'sku' => $product->sku,
                'name' => $product->name,
                'category' => $product->category?->name ?? '-',
                'opening_stock' => round((float) $openingStock, 2),
                'total_in' => round((float) $totalIn, 2),
                'total_out' => round((float) $totalOut, 2),
                'closing_stock' => round((float) $currentStock, 2),
                'avg_stock' => round((float) $avgStock, 2),
                'turnover' => $turnover,
            ];
        });

        $reportData = $reportData->sortByDesc('turnover')->values();

        return view('livewire.inventory-turnover-report', compact('reportData', 'warehouses', 'categories'));
    }
}
