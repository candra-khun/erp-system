<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sku' => 'SKU-'.strtoupper(fake()->unique()->bothify('??####')),
            'name' => fake()->words(3, true),
            'product_category_id' => ProductCategory::factory(),
            'base_unit_id' => ProductUnit::factory(),
            'barcode' => fake()->unique()->ean13(),
            'description' => fake()->paragraph(),
            'purchase_price' => fake()->randomFloat(2, 5000, 100000),
            'selling_price' => fn (array $attrs) => $attrs['purchase_price'] * 1.3,
            'min_stock' => fake()->randomFloat(2, 5, 50),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
