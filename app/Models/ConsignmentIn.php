<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ConsignmentInFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Penerimaan barang konsinyasi dari supplier (PRD Fase 4 #15).
 */
class ConsignmentIn extends Model
{
    /** @use HasFactory<ConsignmentInFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'consignment_number',
        'supplier_id',
        'warehouse_id',
        'received_date',
        'expiry_date',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'received_date' => 'date',
            'expiry_date' => 'date',
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
     * @return HasMany<ConsignmentItem>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ConsignmentItem::class);
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

        return $query->whereIn('consignment_ins.warehouse_id', $warehouseIds);
    }
}
