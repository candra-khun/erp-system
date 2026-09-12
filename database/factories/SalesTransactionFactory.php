<?php

namespace Database\Factories;

use App\Models\SalesTransaction;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesTransaction>
 */
class SalesTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'transaction_number' => 'ST-'.fake()->unique()->numerify('########'),
            'warehouse_id' => Warehouse::factory(),
            'cashier_id' => User::factory(),
            'payment_method' => 'cash',
            'status' => 'completed',
        ];
    }
}
