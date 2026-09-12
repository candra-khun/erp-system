<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\SalesReturn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesReturn>
 */
class SalesReturnFactory extends Factory
{
    public function definition(): array
    {
        $returnable = SalesOrder::factory()->create();

        return [
            'return_number' => 'SR-'.fake()->unique()->numerify('########'),
            'returnable_type' => SalesOrder::class,
            'returnable_id' => $returnable->id,
            'customer_id' => Customer::factory(),
            'return_date' => fake()->date(),
            'status' => 'draft',
        ];
    }
}
