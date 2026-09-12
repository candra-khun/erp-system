<?php

namespace App\Models;

use Database\Factories\SalesReturnFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesReturn extends Model
{
    /** @use HasFactory<SalesReturnFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'return_number',
        'returnable_type',
        'returnable_id',
        'customer_id',
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
     * @return MorphTo<Model>
     */
    public function returnable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Customer>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<SalesReturnItem>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }
}
