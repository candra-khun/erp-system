<?php

namespace App\Models;

use Database\Factories\SalesTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesTransaction extends Model
{
    /** @use HasFactory<SalesTransactionFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transaction_number',
        'warehouse_id',
        'customer_id',
        'pos_shift_id',
        'cashier_id',
        'payment_method',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'change_amount',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Warehouse>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<Customer>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<PosShift>
     */
    public function posShift(): BelongsTo
    {
        return $this->belongsTo(PosShift::class);
    }

    /**
     * @return BelongsTo<User>
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /**
     * @return HasMany<SalesTransactionItem>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SalesTransactionItem::class);
    }

    /**
     * @return MorphMany<SalesReturn>
     */
    public function salesReturns(): MorphMany
    {
        return $this->morphMany(SalesReturn::class, 'returnable');
    }

    /**
     * @return HasMany<SalesTransactionPayment>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SalesTransactionPayment::class);
    }
}
