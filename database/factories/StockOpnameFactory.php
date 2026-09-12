<?php

namespace Database\Factories;

use App\Models\StockOpname;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockOpname>
 */
class StockOpnameFactory extends Factory
{
    public function definition(): array
    {
        return [
            'opname_number' => 'OPN-'.fake()->unique()->numerify('########'),
            'warehouse_id' => Warehouse::factory(),
            'status' => 'draft',
        ];
    }
}
