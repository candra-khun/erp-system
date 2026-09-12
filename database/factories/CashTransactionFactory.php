<?php

namespace Database\Factories;

use App\Models\CashTransaction;
use App\Models\SalesTransaction;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashTransaction>
 */
class CashTransactionFactory extends Factory
{
    public function definition(): array
    {
        $ref = SalesTransaction::factory()->create();

        return [
            'transaction_number' => 'CT-'.fake()->unique()->numerify('########'),
            'warehouse_id' => Warehouse::factory(),
            'type' => fake()->randomElement(['in', 'out']),
            'category' => fake()->randomElement(['sales', 'purchase', 'operational', 'salary', 'other']),
            'amount' => fake()->randomFloat(2, 100, 10000),
            'reference_type' => SalesTransaction::class,
            'reference_id' => $ref->id,
            'transaction_date' => fake()->date(),
        ];
    }
}
