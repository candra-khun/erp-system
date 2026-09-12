<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $productId,
        public readonly int $warehouseId,
        public readonly string $type, // in, out, adjustment, transfer_in, transfer_out
        public readonly float $quantity,
        public readonly ?string $referenceType = null,
        public readonly ?int $referenceId = null,
        public readonly ?int $userId = null,
        public readonly ?string $notes = null,
    ) {}
}
