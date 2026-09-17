<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConsignmentSettlementStatus;
use Database\Factories\ConsignmentSettlementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Settlement konsinyasi: perhitungan barang terjual → hutang ke supplier
 * (PRD Fase 4 #15).
 */
class ConsignmentSettlement extends Model
{
    /** @use HasFactory<ConsignmentSettlementFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'settlement_number',
        'supplier_id',
        'warehouse_id',
        'period_start',
        'period_end',
        'total_quantity_sold',
        'total_amount',
        'commission_amount',
        'account_payable_id',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'total_quantity_sold' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'status' => ConsignmentSettlementStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<AccountPayable, $this>
     */
    public function accountPayable(): BelongsTo
    {
        return $this->belongsTo(AccountPayable::class);
    }

    /**
     * @return HasMany<ConsignmentSettlementItem>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ConsignmentSettlementItem::class);
    }

    /**
     * Scope ke cabang yang dapat diakses user (null = tanpa batasan).
     *
     * @param  ?list<int>  $warehouseIds
     */
    public function scopeAccessibleWarehouse(Builder $query, ?array $warehouseIds): Builder
    {
        if ($warehouseIds === null) {
            return $query;
        }

        return $query->whereIn('consignment_settlements.warehouse_id', $warehouseIds);
    }
}
