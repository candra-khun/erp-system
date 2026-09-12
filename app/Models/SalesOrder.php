<?php

namespace App\Models;

use Database\Factories\SalesOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends Model
{
    /** @use HasFactory<SalesOrderFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'so_number',
        'customer_id',
        'warehouse_id',
        'status',
        'order_date',
        'delivery_date',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'payment_status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'delivery_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Customer>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<SalesOrderItem>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    /**
     * @return MorphMany<SalesReturn>
     */
    public function salesReturns(): MorphMany
    {
        return $this->morphMany(SalesReturn::class, 'returnable');
    }
}
