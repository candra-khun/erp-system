<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    public function definition(): array
    {
        $qty = fake()->randomFloat(2, 1, 100);
        $price = fake()->randomFloat(2, 100, 10000);

        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'product_id' => Product::factory(),
            'quantity' => $qty,
            'unit_price' => $price,
            'subtotal' => $qty * $price,
        ];
    }
}
