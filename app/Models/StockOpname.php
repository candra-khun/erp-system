<?php

namespace App\Models;

use Database\Factories\StockOpnameFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockOpname extends Model
{
    /** @use HasFactory<StockOpnameFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'opname_number',
        'warehouse_id',
        'status',
        'notes',
        'created_by',
        'approved_by',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<StockOpnameItem>
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }
}
