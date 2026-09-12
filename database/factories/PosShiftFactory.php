<?php

namespace Database\Factories;

use App\Models\PosShift;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosShift>
 */
class PosShiftFactory extends Factory
{
    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'user_id' => User::factory(),
            'opened_at' => now(),
            'status' => 'open',
        ];
    }
}
