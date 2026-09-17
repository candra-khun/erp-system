<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPrice;

/**
 * Resolves the effective selling price for a product, honouring tiered
 * (quantity-break) and warehouse-specific price records (PRD 4.1).
 */
class ProductPricingService
{
    /**
     * Resolve the unit price per base unit for a product in a warehouse.
     *
     * Selection order: warehouse-specific rows before global rows, then the
     * highest `min_quantity` tier the requested quantity satisfies. Falls back
     * to the product's master `selling_price` when no tier matches.
     */
    public function resolveUnitPrice(Product $product, ?int $warehouseId, float $quantity): float
    {
        $fallback = (float) $product->selling_price;

        if ($quantity <= 0) {
            return $fallback;
        }

        $today = now()->toDateString();

        $price = ProductPrice::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->where(function ($query) use ($warehouseId): void {
                $query->whereNull('warehouse_id');

                if ($warehouseId !== null) {
                    $query->orWhere('warehouse_id', $warehouseId);
                }
            })
            ->where('min_quantity', '<=', $quantity)
            ->where(function ($query) use ($today): void {
                $query->whereNull('start_date')->orWhere('start_date', '<=', $today);
            })
            ->where(function ($query) use ($today): void {
                $query->whereNull('end_date')->orWhere('end_date', '>=', $today);
            })
            ->orderByRaw('warehouse_id IS NULL')
            ->orderByDesc('min_quantity')
            ->orderByDesc('price')
            ->first();

        return $price ? (float) $price->price : $fallback;
    }
}
