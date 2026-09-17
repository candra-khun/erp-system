<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MarketplaceOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MarketplaceOrder> */
class MarketplaceOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'marketplace_channel_id' => MarketplaceChannel::factory(),
            'channel_order_id' => $this->faker->unique()->uuid(),
            'channel_order_number' => $this->faker->unique()->numerify('ORD-#####'),
            'customer_name' => $this->faker->name(),
            'customer_phone' => $this->faker->optional()->phoneNumber(),
            'customer_email' => $this->faker->optional()->safeEmail(),
            'shipping_address' => $this->faker->optional()->address(),
            'total_amount' => $this->faker->randomFloat(2, 50000, 500000),
            'shipping_cost' => $this->faker->randomFloat(2, 0, 20000),
            'commission' => $this->faker->randomFloat(2, 0, 5000),
            'courier_name' => $this->faker->optional()->randomElement(['JNE', 'J&T', 'SiCepat']),
            'tracking_number' => $this->faker->optional()->numerify('TRK#####'),
            'status' => 'new',
            'order_date' => now()->toDateString(),
        ];
    }
}
