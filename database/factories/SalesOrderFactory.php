<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesOrder>
 */
class SalesOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'so_number' => 'SO-'.fake()->unique()->numerify('########'),
            'customer_id' => Customer::factory(),
            'warehouse_id' => Warehouse::factory(),
            'status' => 'draft',
            'order_date' => fake()->date(),
        ];
    }
}
