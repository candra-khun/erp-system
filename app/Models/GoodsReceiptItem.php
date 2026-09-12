<?php

namespace App\Models;

use Database\Factories\GoodsReceiptItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    /** @use HasFactory<GoodsReceiptItemFactory> */
    use HasFactory;

    protected $fillable = [
        'goods_receipt_id',
        'purchase_order_item_id',
        'product_id',
        'quantity',
        'batch_number',
        'expiry_date',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'expiry_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<GoodsReceipt>
     */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    /**
     * @return BelongsTo<PurchaseOrderItem>
     */
    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    /**
     * @return BelongsTo<Product>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
