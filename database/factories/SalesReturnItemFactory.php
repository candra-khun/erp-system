<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesReturnItem>
 */
class SalesReturnItemFactory extends Factory
{
    public function definition(): array
    {
        $qty = fake()->randomFloat(2, 1, 50);
        $price = fake()->randomFloat(2, 100, 10000);

        return [
            'sales_return_id' => SalesReturn::factory(),
            'product_id' => Product::factory(),
            'quantity' => $qty,
            'unit_price' => $price,
            'subtotal' => $qty * $price,
            'restock' => true,
        ];
    }
}
