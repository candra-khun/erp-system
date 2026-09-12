<?php

namespace App\Models;

use Database\Factories\SalesReturnItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnItem extends Model
{
    /** @use HasFactory<SalesReturnItemFactory> */
    use HasFactory;

    protected $fillable = [
        'sales_return_id',
        'product_id',
        'quantity',
        'unit_price',
        'subtotal',
        'restock',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'restock' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<SalesReturn>
     */
    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }

    /**
     * @return BelongsTo<Product>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
