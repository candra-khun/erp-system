<?php

namespace App\Models;

use Database\Factories\StockOpnameItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpnameItem extends Model
{
    /** @use HasFactory<StockOpnameItemFactory> */
    use HasFactory;

    protected $fillable = [
        'stock_opname_id',
        'product_id',
        'system_quantity',
        'physical_quantity',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'system_quantity' => 'decimal:2',
            'physical_quantity' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    public function stockOpname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
