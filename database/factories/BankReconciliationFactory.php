<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BankReconciliation;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BankReconciliation> */
class BankReconciliationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $start = $this->faker->dateTimeThisYear();

        return [
            'reconciliation_number' => 'BR-'.now()->format('Ymd').'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'warehouse_id' => Warehouse::factory(),
            'period_start' => $start->format('Y-m-d'),
            'period_end' => $start->modify('+1 month')->format('Y-m-d'),
            'bank_statement_balance' => $this->faker->randomFloat(2, 1000, 500000),
            'book_balance' => 0,
            'difference' => 0,
            'status' => 'open',
            'created_by' => User::factory(),
        ];
    }
}
