<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ShipmentStatus;
use App\Models\SalesOrder;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Shipment> */
class ShipmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'shipment_number' => 'SJ-'.now()->format('Ymd').'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'sales_order_id' => SalesOrder::factory(),
            'courier_id' => null,
            'recipient_name' => $this->faker->name(),
            'recipient_phone' => $this->faker->phoneNumber(),
            'destination_address' => $this->faker->address(),
            'total_weight_kg' => $this->faker->randomFloat(2, 0.5, 50),
            'shipping_cost' => $this->faker->randomFloat(2, 5000, 200000),
            'status' => ShipmentStatus::Preparing,
            'notes' => null,
        ];
    }
}
