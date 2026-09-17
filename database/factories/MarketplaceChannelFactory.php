<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MarketplaceChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MarketplaceChannel> */
class MarketplaceChannelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'platform' => $this->faker->randomElement(['tokopedia', 'shopee', 'lazada']),
            'shop_name' => $this->faker->optional()->company(),
            'warehouse_id' => null,
            'api_credential' => null,
            'sync_orders' => true,
            'sync_stock' => true,
            'status' => 'active',
        ];
    }
}
