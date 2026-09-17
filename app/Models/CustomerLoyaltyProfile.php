<?php

namespace App\Models;

use App\Enums\LoyaltyTier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerLoyaltyProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'tier',
        'points_balance',
        'lifetime_points',
        'tier_achieved_at',
    ];

    protected function casts(): array
    {
        return [
            'tier' => LoyaltyTier::class,
            'points_balance' => 'integer',
            'lifetime_points' => 'integer',
            'tier_achieved_at' => 'date',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<LoyaltyPoint, $this> */
    public function pointLogs(): HasMany
    {
        return $this->hasMany(LoyaltyPoint::class, 'customer_id', 'customer_id');
    }
}
