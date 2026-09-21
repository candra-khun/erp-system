<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Attendance> */
class AttendanceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $date = fake()->date();
        $clockIn = strtotime($date.' 08:05:00');
        $clockedOut = fake()->boolean(80);

        return [
            'attendance_number' => 'ATT-'.fake()->unique()->numerify('##########'),
            'employee_id' => Employee::factory(),
            'attendance_date' => $date,
            'work_shift_id' => null,
            'clock_in' => date('Y-m-d H:i:s', $clockIn),
            'clock_out' => $clockedOut ? date('Y-m-d H:i:s', $clockIn + 8 * 3600) : null,
            'status' => fake()->randomElement(AttendanceStatus::cases())->value,
            'late_minutes' => fake()->numberBetween(0, 60),
            'early_out_minutes' => fake()->numberBetween(0, 30),
            'overtime_hours' => fake()->randomFloat(2, 0, 4),
            'check_in_method' => fake()->randomElement(AttendanceMethod::cases())->value,
            'check_out_method' => fake()->randomElement(AttendanceMethod::cases())->value,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
