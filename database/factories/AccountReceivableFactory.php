<?php

namespace Database\Factories;

use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\SalesOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountReceivable>
 */
class AccountReceivableFactory extends Factory
{
    public function definition(): array
    {
        $total = fake()->randomFloat(2, 100, 10000);
        $ref = SalesOrder::factory()->create();

        return [
            'ar_number' => 'AR-'.fake()->unique()->numerify('########'),
            'customer_id' => Customer::factory(),
            'reference_type' => SalesOrder::class,
            'reference_id' => $ref->id,
            'total_amount' => $total,
            'remaining_amount' => $total,
            'due_date' => fake()->dateTimeBetween('+1 day', '+60 days')->format('Y-m-d'),
            'status' => 'open',
        ];
    }
}
