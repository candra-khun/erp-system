<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'type' => fake()->randomElement(['in', 'out', 'adjustment', 'transfer_in', 'transfer_out']),
            'quantity' => fake()->randomFloat(2, 1, 100),
        ];
    }
}
