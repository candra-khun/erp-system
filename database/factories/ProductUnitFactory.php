<?php

namespace Database\Factories;

use App\Models\ProductUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductUnit>
 */
class ProductUnitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Pcs', 'Box', 'Dus', 'Kg', 'Liter', 'Meter', 'Pack', 'Sachet']),
            'symbol' => fake()->randomElement(['pcs', 'box', 'dus', 'kg', 'l', 'm', 'pack', 'sct']),
            'conversion_factor' => 1,
            'is_base' => true,
        ];
    }

    public function derived(ProductUnit $baseUnit, float $factor): static
    {
        return $this->state([
            'base_unit_id' => $baseUnit->id,
            'conversion_factor' => $factor,
            'is_base' => false,
        ]);
    }
}
