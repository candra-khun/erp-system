<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sku',
        'name',
        'product_category_id',
        'base_unit_id',
        'barcode',
        'description',
        'image_path',
        'purchase_price',
        'selling_price',
        'min_stock',
        'is_active',
        'reorder_point',
        'reorder_alert_enabled',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'min_stock' => 'decimal:2',
            'reorder_point' => 'decimal:2',
            'is_active' => 'boolean',
            'reorder_alert_enabled' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ProductCategory>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /**
     * @return BelongsTo<ProductUnit>
     */
    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'base_unit_id');
    }

    /**
     * @return HasMany<Stock>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    /**
     * @return HasMany<StockMovement>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * @return HasMany<ProductPrice>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    /**
     * @return HasMany<ProductUnit>
     */
    public function derivedUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class, 'base_unit_id', 'base_unit_id');
    }

    /**
     * Convert a quantity from a given unit to the base unit.
     */
    public function convertToBaseUnit(float $quantity, ProductUnit $unit): float
    {
        if ($unit->is_base) {
            return $quantity;
        }

        return $quantity * (float) $unit->conversion_factor;
    }

    /**
     * Convert a quantity from the base unit to a given unit.
     */
    public function convertFromBaseUnit(float $quantity, ProductUnit $unit): float
    {
        if ($unit->is_base) {
            return $quantity;
        }

        $factor = (float) $unit->conversion_factor;

        if ($factor == 0.0) {
            throw new \RuntimeException("Conversion factor for unit {$unit->id} is zero.");
        }

        return $quantity / $factor;
    }
}
