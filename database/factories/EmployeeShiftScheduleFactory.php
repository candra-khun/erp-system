<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeShiftSchedule;
use App\Models\WorkShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmployeeShiftSchedule> */
class EmployeeShiftScheduleFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'work_shift_id' => WorkShift::factory(),
            'effective_date' => fake()->date(),
            'end_date' => null,
            'source' => fake()->randomElement(['manual', 'recurring']),
        ];
    }
}
