<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GatewayCredential;
use App\Models\GatewayTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GatewayTransaction> */
class GatewayTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'gateway_credential_id' => GatewayCredential::factory(),
            'transaction_number' => $this->faker->unique()->numerify('GT-#####'),
            'gateway_transaction_id' => $this->faker->unique()->uuid(),
            'provider' => 'manual',
            'payment_channel' => $this->faker->randomElement(['qris', 'virtual_account', 'credit_card']),
            'payment_reference' => null,
            'amount' => $this->faker->randomFloat(2, 10000, 500000),
            'fee_amount' => 0,
            'settlement_amount' => 0,
            'currency' => 'IDR',
            'status' => 'pending',
            'expires_at' => now()->addDay(),
            'paid_at' => null,
            'payload_request' => null,
            'payload_response' => null,
        ];
    }
}
