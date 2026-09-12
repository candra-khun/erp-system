<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'WH-'.strtoupper(fake()->unique()->numerify('##')),
            'name' => fake()->city().' '.fake()->randomElement(['Warehouse', 'Branch', 'Store']),
            'type' => fake()->randomElement(['warehouse', 'branch', 'store']),
            'address' => fake()->address(),
            'city' => fake()->city(),
            'province' => fake()->state(),
            'phone' => fake()->phoneNumber(),
            'manager_name' => fake()->name(),
            'is_active' => true,
        ];
    }

    public function branch(): static
    {
        return $this->state(['type' => 'branch']);
    }

    public function storeType(): static
    {
        return $this->state(['type' => 'store']);
    }
}
