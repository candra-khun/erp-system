<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PayrollComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PayrollComponent> */
class PayrollComponentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Tunjangan Transport', 'Tunjangan Makan', 'Tunjangan Kesehatan', 'Potongan BPJS', 'Potongan Pinjaman', 'THR']),
            'type' => $this->faker->randomElement(['earning', 'deduction', 'tax']),
            'calculation_type' => 'fixed',
            'amount' => $this->faker->randomFloat(2, 50000, 2000000),
            'percentage' => 0,
            'is_taxable' => $this->faker->boolean(),
            'is_active' => true,
        ];
    }
}
