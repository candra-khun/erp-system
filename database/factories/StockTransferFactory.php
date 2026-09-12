<?php

namespace Database\Factories;

use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransfer>
 */
class StockTransferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'transfer_number' => 'TRF-'.fake()->unique()->numerify('########'),
            'source_warehouse_id' => Warehouse::factory(),
            'destination_warehouse_id' => Warehouse::factory(),
            'status' => 'draft',
        ];
    }
}
