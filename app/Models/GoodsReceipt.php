<?php

namespace App\Models;

use Database\Factories\GoodsReceiptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsReceipt extends Model
{
    /** @use HasFactory<GoodsReceiptFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'grn_number',
        'purchase_order_id',
        'warehouse_id',
        'receipt_date',
        'notes',
        'received_by',
    ];

    protected function casts(): array
    {
        return [
            'receipt_date' => 'date',
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
     * @return BelongsTo<Warehouse>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<User>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return HasMany<GoodsReceiptItem>
     */
    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }
}
