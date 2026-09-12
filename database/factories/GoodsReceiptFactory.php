<?php

namespace Database\Factories;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsReceipt>
 */
class GoodsReceiptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'grn_number' => 'GRN-'.fake()->unique()->numerify('########'),
            'purchase_order_id' => PurchaseOrder::factory(),
            'warehouse_id' => Warehouse::factory(),
            'receipt_date' => fake()->date(),
        ];
    }
}
