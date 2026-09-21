<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\AttendanceValidation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AttendanceValidation> */
class AttendanceValidationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'attendance_id' => Attendance::factory(),
            'method' => fake()->randomElement(['pin', 'barcode', 'fingerprint', 'gps', 'selfie']),
            'device_id' => fake()->optional()->bothify('DEV-####'),
            'direction' => fake()->randomElement(['in', 'out']),
            'validated_at' => fake()->dateTime(),
            'latitude' => fake()->optional()->latitude(),
            'longitude' => fake()->optional()->longitude(),
            'location_name' => fake()->optional()->words(3, true),
            'metadata' => null,
        ];
    }
}
