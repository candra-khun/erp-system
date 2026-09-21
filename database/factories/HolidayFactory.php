<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Holiday> */
class HolidayFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Tahun Baru Masehi',
                'Hari Kemerdekaan RI',
                'Hari Raya Idul Fitri',
                'Hari Buruh Internasional',
                'Libur Perusahaan',
            ]),
            'holiday_date' => fake()->date(),
            'type' => fake()->randomElement(['national', 'religious', 'company', 'weekly_off']),
            'is_recurring_annual' => fake()->boolean(60),
            'weekly_day_of_week' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
