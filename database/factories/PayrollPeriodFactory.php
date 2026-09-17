<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PayrollPeriod;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PayrollPeriod> */
class PayrollPeriodFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $year = $this->faker->year();
        $month = $this->faker->numberBetween(1, 12);

        return [
            'period_number' => 'PY-'.$year.str_pad((string) $month, 2, '0', STR_PAD_LEFT).'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'year' => (int) $year,
            'month' => $month,
            'warehouse_id' => Warehouse::factory(),
            'start_date' => $year.'-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT).'-01',
            'end_date' => $year.'-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT).'-28',
            'payment_date' => $year.'-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT).'-28',
            'status' => 'draft',
        ];
    }
}
