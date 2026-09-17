<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MarketplaceChannelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Channel marketplace (Tokopedia/Shopee/dll) — PRD Fase 4 #13.
 */
class MarketplaceChannel extends Model
{
    /** @use HasFactory<MarketplaceChannelFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'platform',
        'shop_name',
        'shop_id',
        'warehouse_id',
        'api_credential',
        'status_callback_url',
        'sync_orders',
        'sync_stock',
        'last_synced_at',
        'status',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'sync_orders' => 'boolean',
            'sync_stock' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * Akses aman ke kredensial (jangan expose di serialisasi).
     */
    public function credential(string $key, mixed $default = null): mixed
    {
        $payload = json_decode((string) $this->api_credential, true);

        if (! is_array($payload)) {
            return $default;
        }

        return $payload[$key] ?? $default;
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return HasMany<MarketplaceOrder>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(MarketplaceOrder::class);
    }

    /**
     * @return HasMany<MarketplaceProductSync>
     */
    public function productSyncs(): HasMany
    {
        return $this->hasMany(MarketplaceProductSync::class);
    }

    /**
     * @return HasMany<MarketplaceSyncLog>
     */
    public function syncLogs(): HasMany
    {
        return $this->hasMany(MarketplaceSyncLog::class);
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

        return $query->whereIn('marketplace_channels.warehouse_id', $warehouseIds);
    }
}
