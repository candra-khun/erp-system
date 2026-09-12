<?php

namespace App\Listeners;

use App\Events\StockChanged;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;

class UpdateStockOnMovement
{
    public function handle(StockChanged $event): void
    {
        DB::transaction(function () use ($event) {
            $stock = Stock::firstOrCreate(
                [
                    'product_id' => $event->productId,
                    'warehouse_id' => $event->warehouseId,
                ],
                ['quantity' => 0]
            );

            // Reload to ensure we have the latest quantity from DB
            $stock->refresh();

            if ($event->type === 'adjustment') {
                $stock->quantity = (float) $stock->quantity + $event->quantity;
            } elseif (in_array($event->type, ['in', 'transfer_in'], true)) {
                $stock->quantity = (float) $stock->quantity + abs($event->quantity);
            } else {
                $stock->quantity = (float) $stock->quantity - abs($event->quantity);
            }

            if ($stock->quantity < 0) {
                throw new \RuntimeException(
                    "Insufficient stock for product ID {$event->productId} in warehouse ID {$event->warehouseId}. "
                    ."Current: {$event->quantity}, Requested: ".abs($event->quantity)
                );
            }

            $stock->save();
        });
    }
}
