<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransferItem>
 */
class StockTransferItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'stock_transfer_id' => StockTransfer::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->randomFloat(2, 1, 100),
        ];
    }
}
