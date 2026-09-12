<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'SUP-'.strtoupper(fake()->unique()->numerify('###')),
            'name' => fake()->company(),
            'contact_person' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'address' => fake()->address(),
            'city' => fake()->city(),
            'province' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'payment_terms_days' => fake()->randomElement([0, 7, 14, 30]),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
