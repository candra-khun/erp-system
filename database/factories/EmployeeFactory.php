<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Employee> */
class EmployeeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'employee_number' => 'EMP-'.fake()->unique()->numerify('#####'),
            'full_name' => fake()->name(),
            'id_card_number' => fake()->optional()->numerify('################'),
            'npwp' => fake()->optional()->numerify('##.###.###.#-###.###'),
            'warehouse_id' => Warehouse::factory(),
            'position' => fake()->randomElement(['Kasir', 'Staff Gudang', 'Admin', 'Sales']),
            'department' => fake()->randomElement(['Operasional', 'Pembelian', 'Penjualan', 'Keuangan']),
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'address' => fake()->optional()->address(),
            'hire_date' => fake()->date(),
            'bank_account_number' => fake()->optional()->numerify('############'),
            'bank_name' => fake()->optional()->randomElement(['BCA', 'Mandiri', 'BNI', 'BRI']),
            'basic_salary' => fake()->randomFloat(2, 1500000, 15000000),
            'payment_type' => 'monthly',
            'tax_status' => fake()->randomElement(['non_taxable', 'taxable']),
            'is_active' => true,
        ];
    }
}
