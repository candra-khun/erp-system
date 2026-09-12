<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntryLine>
 */
class JournalEntryLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'journal_entry_id' => JournalEntry::factory(),
            'account_id' => Account::factory(),
            'type' => fake()->randomElement(['debit', 'credit']),
            'amount' => fake()->randomFloat(2, 100, 10000),
        ];
    }
}
