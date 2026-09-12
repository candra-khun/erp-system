<?php

namespace Database\Factories;

use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'journal_number' => 'JE-'.fake()->unique()->numerify('########'),
            'journal_date' => fake()->date(),
            'type' => fake()->randomElement(['general', 'sales', 'purchase', 'adjustment', 'closing']),
            'is_posted' => false,
        ];
    }
}
