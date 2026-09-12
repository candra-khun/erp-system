<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\SalesTransaction;
use App\Models\SalesTransactionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesTransactionItem>
 */
class SalesTransactionItemFactory extends Factory
{
    public function definition(): array
    {
        $qty = fake()->randomFloat(2, 1, 100);
        $price = fake()->randomFloat(2, 100, 10000);

        return [
            'sales_transaction_id' => SalesTransaction::factory(),
            'product_id' => Product::factory(),
            'quantity' => $qty,
            'unit_price' => $price,
            'subtotal' => $qty * $price,
        ];
    }
}
