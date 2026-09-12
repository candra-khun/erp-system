<?php

namespace Database\Factories;

use App\Models\AccountPayable;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountPayable>
 */
class AccountPayableFactory extends Factory
{
    public function definition(): array
    {
        $total = fake()->randomFloat(2, 100, 10000);
        $ref = PurchaseOrder::factory()->create();

        return [
            'ap_number' => 'AP-'.fake()->unique()->numerify('########'),
            'supplier_id' => Supplier::factory(),
            'reference_type' => PurchaseOrder::class,
            'reference_id' => $ref->id,
            'total_amount' => $total,
            'remaining_amount' => $total,
            'due_date' => fake()->dateTimeBetween('+1 day', '+60 days')->format('Y-m-d'),
            'status' => 'open',
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'open',
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'paid_amount' => $attributes['total_amount'] ?? 0,
            'remaining_amount' => 0,
        ]);
    }

    public function partial(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'partial',
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'overdue',
            'due_date' => fake()->dateTimeBetween('-30 days', '-1 day')->format('Y-m-d'),
        ]);
    }
}
