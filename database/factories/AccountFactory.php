<?php

namespace Database\Factories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'ACC-'.fake()->unique()->numerify('####'),
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(['asset', 'liability', 'equity', 'revenue', 'expense']),
            'is_active' => true,
        ];
    }

    public function asset(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'asset',
        ]);
    }

    public function liability(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'liability',
        ]);
    }

    public function equity(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'equity',
        ]);
    }

    public function revenue(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'revenue',
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'expense',
        ]);
    }
}
