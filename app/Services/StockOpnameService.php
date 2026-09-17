<?php

namespace App\Services;

use App\Events\StockChanged;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockOpname;
use Illuminate\Support\Facades\DB;

class StockOpnameService
{
    public function __construct(private readonly TransactionAuditLogger $audit) {}

    /**
     * Approve a completed stock opname and apply adjustments to stock.
     * Each difference triggers a StockChanged event so the standard
     * UpdateStockOnMovement listener keeps stocks consistent.
     */
    public function approve(StockOpname $opname, int $approvedByUserId): void
    {
        if (! in_array($opname->status, ['draft', 'completed'], true)) {
            throw new \RuntimeException('Only draft or completed opnames can be approved.');
        }

        DB::transaction(function () use ($opname, $approvedByUserId): void {
            $items = $opname->items()->with('product')->get();

            foreach ($items as $item) {
                $diff = (float) $item->difference;

                if (abs($diff) < 0.001) {
                    continue;
                }

                // Fire the standard stock-change event so existing listeners
                // create the StockMovement row and adjust the Stock record.
                // Signed quantity on 'adjustment' type: positive adds, negative subtracts.
                event(new StockChanged(
                    productId: $item->product_id,
                    warehouseId: $opname->warehouse_id,
                    type: 'adjustment',
                    quantity: $diff,
                    referenceType: StockOpname::class,
                    referenceId: $opname->id,
                    userId: $approvedByUserId,
                    notes: 'Stock opname adjustment '.$opname->opname_number,
                ));
            }

            $opname->update([
                'status' => 'approved',
                'approved_by' => $approvedByUserId,
                'completed_at' => $opname->completed_at ?? now(),
            ]);

            $this->audit->log(
                $opname,
                'stock_opname_approved',
                null,
                [
                    'opname_number' => $opname->opname_number,
                    'adjusted_items' => collect($items)
                        ->filter(fn ($item) => abs((float) $item->difference) >= 0.001)
                        ->map(fn ($item) => [
                            'product_id' => $item->product_id,
                            'difference' => (float) $item->difference,
                        ])
                        ->values()
                        ->all(),
                ],
                $approvedByUserId,
            );
        });
    }

    /**
     * Check reorder points for all products in a given warehouse.
     * Returns an array of products that are below their reorder point.
     *
     * @return array<int, array{product_id: int, sku: string, name: string, current_stock: float, reorder_point: float}>
     */
    public function checkReorderAlerts(int $warehouseId): array
    {
        $alerts = [];

        $stocks = Stock::where('warehouse_id', $warehouseId)
            ->with('product')
            ->get();

        foreach ($stocks as $stock) {
            $product = $stock->product;

            if (! $product || ! $product->reorder_alert_enabled || $product->reorder_point === null) {
                continue;
            }

            $currentQty = (float) $stock->quantity;
            $reorderPoint = (float) $product->reorder_point;

            if ($currentQty <= $reorderPoint) {
                $alerts[] = [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'current_stock' => $currentQty,
                    'reorder_point' => $reorderPoint,
                ];
            }
        }

        return $alerts;
    }
}
