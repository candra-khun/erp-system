<?php

namespace Database\Factories;

use App\Models\Courier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Courier>
 */
class CourierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'KUR-'.strtoupper(fake()->unique()->numerify('###')),
            'name' => fake()->randomElement(['JNE', 'J&T', 'SiCepat', 'AnterAja', 'Ninja Express', 'Kurir Internal']).' '.fake()->numberBetween(1, 99),
            'type' => fake()->randomElement(['internal', 'external']),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'address' => fake()->address(),
            'cost_per_kg' => fake()->randomFloat(2, 5000, 25000),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
