<?php

namespace App\Models;

use Database\Factories\PosShiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosShift extends Model
{
    /** @use HasFactory<PosShiftFactory> */
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'user_id',
        'opened_at',
        'closed_at',
        'opening_cash',
        'closing_cash',
        'expected_cash',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_cash' => 'decimal:2',
            'closing_cash' => 'decimal:2',
            'expected_cash' => 'decimal:2',
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
     * @return BelongsTo<User>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<SalesTransaction>
     */
    public function salesTransactions(): HasMany
    {
        return $this->hasMany(SalesTransaction::class);
    }
}
