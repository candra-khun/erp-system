<?php

namespace App\Models;

use Database\Factories\ProductUnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductUnit extends Model
{
    /** @use HasFactory<ProductUnitFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'symbol',
        'conversion_factor',
        'base_unit_id',
        'is_base',
    ];

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:4',
            'is_base' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ProductUnit>
     */
    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'base_unit_id');
    }

    /**
     * @return HasMany<ProductUnit>
     */
    public function derivedUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class, 'base_unit_id');
    }

    /**
     * @return HasMany<Product>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'base_unit_id');
    }
}
