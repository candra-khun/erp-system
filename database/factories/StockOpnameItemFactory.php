<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockOpnameItem>
 */
class StockOpnameItemFactory extends Factory
{
    public function definition(): array
    {
        $sysQty = fake()->randomFloat(2, 0, 500);

        return [
            'stock_opname_id' => StockOpname::factory(),
            'product_id' => Product::factory(),
            'system_quantity' => $sysQty,
            'physical_quantity' => $sysQty + fake()->randomFloat(2, -5, 5),
        ];
    }
}
