<?php

namespace App\Models;

use Database\Factories\StockTransferItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItem extends Model
{
    /** @use HasFactory<StockTransferItemFactory> */
    use HasFactory;

    protected $fillable = [
        'stock_transfer_id',
        'product_id',
        'quantity',
        'received_quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'received_quantity' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<StockTransfer>
     */
    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    /**
     * @return BelongsTo<Product>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
