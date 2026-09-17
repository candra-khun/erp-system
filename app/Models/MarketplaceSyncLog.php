<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MarketplaceSyncLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log sinkronisasi marketplace (audit) — PRD Fase 4 #13.
 */
class MarketplaceSyncLog extends Model
{
    /** @use HasFactory<MarketplaceSyncLogFactory> */
    use HasFactory;

    protected $fillable = [
        'marketplace_channel_id',
        'direction',
        'entity',
        'status',
        'reference',
        'message',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<MarketplaceChannel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(MarketplaceChannel::class, 'marketplace_channel_id');
    }
}
