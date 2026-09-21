<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Employee;
use App\Models\OvertimeRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OvertimeRequest> */
class OvertimeRequestFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $date = fake()->date();
        $start = strtotime($date.' 17:00:00');
        $hours = fake()->numberBetween(1, 4);

        return [
            'overtime_number' => 'OT-'.fake()->unique()->numerify('##########'),
            'employee_id' => Employee::factory(),
            'overtime_date' => $date,
            'start_time' => date('Y-m-d H:i:s', $start),
            'end_time' => date('Y-m-d H:i:s', $start + $hours * 3600),
            'hours' => $hours,
            'description' => fake()->optional()->sentence(),
            'status' => 'pending',
        ];
    }
}
