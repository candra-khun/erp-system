<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ConsignmentSettlementItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Detail item settlement konsinyasi (PRD Fase 4 #15).
 */
class ConsignmentSettlementItem extends Model
{
    /** @use HasFactory<ConsignmentSettlementItemFactory> */
    use HasFactory;

    protected $fillable = [
        'consignment_settlement_id',
        'consignment_item_id',
        'product_id',
        'quantity_sold',
        'consignment_price',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity_sold' => 'decimal:2',
            'consignment_price' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<ConsignmentSettlement, $this>
     */
    public function settlement(): BelongsTo
    {
        return $this->belongsTo(ConsignmentSettlement::class, 'consignment_settlement_id');
    }

    /**
     * @return BelongsTo<ConsignmentItem, $this>
     */
    public function consignmentItem(): BelongsTo
    {
        return $this->belongsTo(ConsignmentItem::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
