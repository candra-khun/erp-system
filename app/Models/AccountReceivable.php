<?php

namespace App\Models;

use Database\Factories\AccountReceivableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountReceivable extends Model
{
    /** @use HasFactory<AccountReceivableFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'warehouse_id',
        'reference_type',
        'reference_id',
        'ar_number',
        'due_date',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
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
     * @return MorphTo<Model>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
