<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GatewayCredential;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GatewayCredential> */
class GatewayCredentialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'provider' => 'manual',
            'merchant_id' => $this->faker->optional()->numerify('MID-#####'),
            'api_key' => $this->faker->optional()->sha256(),
            'server_key' => $this->faker->optional()->sha256(),
            'additional_config' => null,
            'warehouse_id' => null,
            'sandbox_mode' => true,
            'is_active' => true,
        ];
    }
}
