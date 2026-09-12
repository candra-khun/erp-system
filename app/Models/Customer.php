<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'type',
        'contact_person',
        'phone',
        'email',
        'address',
        'city',
        'province',
        'postal_code',
        'credit_limit',
        'payment_terms_days',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'integer',
            'payment_terms_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SalesOrder>
     */
    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    /**
     * @return HasMany<SalesTransaction>
     */
    public function salesTransactions(): HasMany
    {
        return $this->hasMany(SalesTransaction::class);
    }
}
