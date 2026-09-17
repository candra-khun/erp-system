<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ConsignmentIn;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ConsignmentIn> */
class ConsignmentInFactory extends Factory
{
    public function definition(): array
    {
        return [
            'consignment_number' => $this->faker->unique()->numerify('CSG-#####'),
            'supplier_id' => Supplier::factory(),
            'warehouse_id' => null,
            'received_date' => now()->toDateString(),
            'expiry_date' => $this->faker->optional()->dateTimeBetween('now', '+90 days')?->format('Y-m-d'),
            'status' => 'open',
            'notes' => null,
            'created_by' => null,
        ];
    }
}
