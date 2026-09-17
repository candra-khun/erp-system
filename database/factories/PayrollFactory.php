<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payroll> */
class PayrollFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $basic = $this->faker->randomFloat(2, 1500000, 15000000);
        $earnings = $this->faker->randomFloat(2, 0, 2000000);
        $deductions = $this->faker->randomFloat(2, 0, 1000000);
        $tax = $this->faker->randomFloat(2, 0, 500000);
        $net = round($basic + $earnings - $deductions - $tax, 2);

        return [
            'payroll_number' => 'PR-'.now()->format('Ymd').'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'payroll_period_id' => PayrollPeriod::factory(),
            'employee_id' => Employee::factory(),
            'basic_salary' => $basic,
            'total_earnings' => $earnings,
            'total_deductions' => $deductions,
            'tax_amount' => $tax,
            'net_salary' => $net,
            'working_days' => 22,
            'overtime_hours' => $this->faker->randomFloat(2, 0, 20),
            'overtime_amount' => $this->faker->randomFloat(2, 0, 500000),
            'status' => 'draft',
        ];
    }
}
