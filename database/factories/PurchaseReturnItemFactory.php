<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseReturnItem>
 */
class PurchaseReturnItemFactory extends Factory
{
    public function definition(): array
    {
        $qty = fake()->randomFloat(2, 1, 50);
        $price = fake()->randomFloat(2, 100, 10000);

        return [
            'purchase_return_id' => PurchaseReturn::factory(),
            'product_id' => Product::factory(),
            'quantity' => $qty,
            'unit_price' => $price,
            'subtotal' => $qty * $price,
        ];
    }
}
