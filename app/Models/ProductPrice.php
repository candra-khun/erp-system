<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductPrice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'price_type',
        'price',
        'min_quantity',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'min_quantity' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Product>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Warehouse>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
