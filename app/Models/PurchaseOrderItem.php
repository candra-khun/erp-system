<?php

namespace App\Models;

use Database\Factories\PurchaseOrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    /** @use HasFactory<PurchaseOrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'quantity',
        'received_quantity',
        'unit_price',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'received_quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PurchaseOrder>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<Product>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<GoodsReceiptItem>
     */
    public function goodsReceiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }
}
