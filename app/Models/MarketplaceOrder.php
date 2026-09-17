<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MarketplaceOrderStatus;
use Database\Factories\MarketplaceOrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Order dari marketplace yang dikonversi ke sales order — PRD Fase 4 #13.
 */
class MarketplaceOrder extends Model
{
    /** @use HasFactory<MarketplaceOrderFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'channel_order_id',
        'channel_order_number',
        'marketplace_channel_id',
        'sales_order_id',
        'customer_name',
        'customer_phone',
        'shipping_address',
        'courier_name',
        'tracking_number',
        'total_amount',
        'shipping_cost',
        'commission',
        'payload',
        'status',
        'synced_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'commission' => 'decimal:2',
            'payload' => 'array',
            'synced_at' => 'datetime',
            'order_date' => 'date',
            'status' => MarketplaceOrderStatus::class,
        ];
    }

    /**
     * @return BelongsTo<MarketplaceChannel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(MarketplaceChannel::class, 'marketplace_channel_id');
    }

    /**
     * @return BelongsTo<SalesOrder, $this>
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /**
     * Scope order milik channel di cabang yang dapat diakses user.
     *
     * @param  ?list<int>  $warehouseIds
     */
    public function scopeAccessibleWarehouse(Builder $query, ?array $warehouseIds): Builder
    {
        if ($warehouseIds === null) {
            return $query;
        }

        return $query->whereHas('channel', fn ($channel) => $channel->whereIn('marketplace_channels.warehouse_id', $warehouseIds));
    }
}
