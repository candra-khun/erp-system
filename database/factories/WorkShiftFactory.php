<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WorkShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkShift> */
class WorkShiftFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $startHour = fake()->randomElement([6, 7, 8, 9, 14, 15, 21]);

        return [
            'name' => fake()->randomElement(['Shift Pagi', 'Shift Siang', 'Shift Malam', 'Shift Reguler']),
            'start_time' => sprintf('%02d:00:00', $startHour),
            'end_time' => sprintf('%02d:00:00', ($startHour + 8) % 24),
            'late_tolerance_minutes' => fake()->randomElement([0, 5, 10, 15]),
            'early_leave_tolerance_minutes' => fake()->randomElement([0, 5, 10]),
            'is_active' => true,
        ];
    }
}
