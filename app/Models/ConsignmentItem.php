<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ConsignmentItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Detail barang konsinyasi: stok titipan per produk (PRD Fase 4 #15).
 */
class ConsignmentItem extends Model
{
    /** @use HasFactory<ConsignmentItemFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'consignment_in_id',
        'product_id',
        'quantity_received',
        'quantity_sold',
        'quantity_returned',
        'quantity_available',
        'consignment_price',
        'selling_price',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity_received' => 'decimal:2',
            'quantity_sold' => 'decimal:2',
            'quantity_returned' => 'decimal:2',
            'quantity_available' => 'decimal:2',
            'consignment_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<ConsignmentIn, $this>
     */
    public function consignmentIn(): BelongsTo
    {
        return $this->belongsTo(ConsignmentIn::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Hitung ulang ketersediaan: diterima - terjual - dikembalikan.
     */
    public function recalculateAvailable(): void
    {
        $available = (float) $this->quantity_received - (float) $this->quantity_sold - (float) $this->quantity_returned;

        $this->update(['quantity_available' => round(max(0, $available), 2)]);
    }
}
