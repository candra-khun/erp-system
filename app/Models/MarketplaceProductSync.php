<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MarketplaceProductSyncFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Mapping produk master ↔ produk di channel — PRD Fase 4 #13.
 */
class MarketplaceProductSync extends Model
{
    /** @use HasFactory<MarketplaceProductSyncFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'marketplace_channel_id',
        'product_id',
        'channel_product_id',
        'channel_url',
        'channel_price',
        'channel_stock',
        'last_synced_at',
        'sync_status',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'channel_price' => 'decimal:2',
            'channel_stock' => 'decimal:2',
            'last_synced_at' => 'datetime',
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
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
