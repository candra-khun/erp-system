<?php

namespace App\Models;

use Database\Factories\PurchaseReturnFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReturn extends Model
{
    /** @use HasFactory<PurchaseReturnFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'return_number',
        'purchase_order_id',
        'supplier_id',
        'return_date',
        'status',
        'total_amount',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'total_amount' => 'decimal:2',
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
     * @return BelongsTo<Supplier>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<User>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<PurchaseReturnItem>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }
}
