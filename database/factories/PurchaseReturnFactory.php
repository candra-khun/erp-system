<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseReturn>
 */
class PurchaseReturnFactory extends Factory
{
    public function definition(): array
    {
        return [
            'return_number' => 'PR-'.fake()->unique()->numerify('########'),
            'purchase_order_id' => PurchaseOrder::factory(),
            'supplier_id' => Supplier::factory(),
            'return_date' => fake()->date(),
            'status' => 'draft',
        ];
    }
}
