<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'CUST-'.strtoupper(fake()->unique()->numerify('###')),
            'name' => fake()->name(),
            'type' => fake()->randomElement(['general', 'member', 'reseller']),
            'contact_person' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'address' => fake()->address(),
            'city' => fake()->city(),
            'province' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'credit_limit' => fake()->randomNumber(6),
            'payment_terms_days' => fake()->randomElement([0, 7, 14, 30]),
            'is_active' => true,
        ];
    }

    public function reseller(): static
    {
        return $this->state(['type' => 'reseller']);
    }

    public function member(): static
    {
        return $this->state(['type' => 'member']);
    }
}
