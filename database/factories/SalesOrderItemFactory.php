<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesOrderItem>
 */
class SalesOrderItemFactory extends Factory
{
    public function definition(): array
    {
        $qty = fake()->randomFloat(2, 1, 100);
        $price = fake()->randomFloat(2, 100, 10000);

        return [
            'sales_order_id' => SalesOrder::factory(),
            'product_id' => Product::factory(),
            'quantity' => $qty,
            'unit_price' => $price,
            'subtotal' => $qty * $price,
        ];
    }
}
