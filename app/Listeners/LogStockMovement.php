<?php

namespace App\Listeners;

use App\Events\StockChanged;
use App\Models\StockMovement;

class LogStockMovement
{
    public function handle(StockChanged $event): void
    {
        StockMovement::create([
            'product_id' => $event->productId,
            'warehouse_id' => $event->warehouseId,
            'type' => $event->type,
            'quantity' => abs($event->quantity),
            'reference_type' => $event->referenceType,
            'reference_id' => $event->referenceId,
            'notes' => $event->notes,
            'created_by' => $event->userId,
            'movement_date' => now(),
        ]);
    }
}
