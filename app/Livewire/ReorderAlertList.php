<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithWarehouseAccess;
use App\Models\Stock;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.erp', ['title' => 'Peringatan Stok Rendah'])]
class ReorderAlertList extends Component
{
    use InteractsWithWarehouseAccess;

    public function render(): View
    {
        $alerts = Stock::with(['product', 'warehouse'])
            ->join('products', 'stocks.product_id', '=', 'products.id')
            ->whereColumn('stocks.quantity', '<=', 'products.reorder_point')
            ->where('products.is_active', true)
            ->whereNotNull('products.reorder_point')
            ->where('products.reorder_point', '>', 0)
            ->when($this->accessibleWarehouseIds() !== null, function ($q): void {
                $q->whereIn('stocks.warehouse_id', $this->accessibleWarehouseIds());
            })
            ->select('stocks.*')
            ->get()
            ->map(function (Stock $stock): array {
                $reorderPoint = (float) ($stock->product->reorder_point ?? 0);
                $currentQty = (float) $stock->quantity;
                $isCritical = $reorderPoint > 0 && $currentQty < ($reorderPoint * 0.5);

                return [
                    'sku' => $stock->product->sku ?? '-',
                    'name' => $stock->product->name ?? '-',
                    'warehouse' => $stock->warehouse->name ?? '-',
                    'current_stock' => $currentQty,
                    'reorder_point' => $reorderPoint,
                    'deficit' => $reorderPoint - $currentQty,
                    'is_critical' => $isCritical,
                ];
            })
            ->sortByDesc('is_critical')
            ->values();

        return view('livewire.reorder-alert-list', compact('alerts'));
    }
}
