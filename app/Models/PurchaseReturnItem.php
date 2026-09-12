<?php

namespace App\Models;

use Database\Factories\PurchaseReturnItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturnItem extends Model
{
    /** @use HasFactory<PurchaseReturnItemFactory> */
    use HasFactory;

    protected $fillable = [
        'purchase_return_id',
        'product_id',
        'quantity',
        'unit_price',
        'subtotal',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PurchaseReturn>
     */
    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    /**
     * @return BelongsTo<Product>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
