<?php

namespace App\Models;

use Database\Factories\SalesTransactionItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesTransactionItem extends Model
{
    /** @use HasFactory<SalesTransactionItemFactory> */
    use HasFactory;

    protected $fillable = [
        'sales_transaction_id',
        'product_id',
        'quantity',
        'unit_price',
        'discount_amount',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<SalesTransaction>
     */
    public function salesTransaction(): BelongsTo
    {
        return $this->belongsTo(SalesTransaction::class);
    }

    /**
     * @return BelongsTo<Product>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
