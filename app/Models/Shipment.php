<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'shipment_number',
        'sales_order_id',
        'courier_id',
        'recipient_name',
        'recipient_phone',
        'destination_address',
        'total_weight_kg',
        'shipping_cost',
        'status',
        'dispatched_at',
        'delivered_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'total_weight_kg' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SalesOrder, $this> */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /**
     * Cabang/gudang shipment — diturunkan dari sales order (PRD §2: scoping user↔cabang).
     */
    public function warehouse(): ?Warehouse
    {
        return $this->salesOrder?->warehouse;
    }

    /**
     * Scope ke cabang yang dapat diakses user (null = tanpa batasan).
     *
     * @param  list<int>|null  $warehouseIds
     */
    public function scopeAccessibleWarehouse(Builder $query, ?array $warehouseIds): void
    {
        if ($warehouseIds === null) {
            return;
        }

        $query->whereHas('salesOrder', fn ($so) => $so->whereIn('warehouse_id', $warehouseIds));
    }

    /** @return BelongsTo<Courier, $this> */
    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
