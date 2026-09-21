<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\HolidayType;
use App\Models\Holiday;
use App\Models\WorkShift;
use Illuminate\Database\Seeder;

/**
 * Data awal modul absensi: shift standar dan kalender libur.
 */
class AttendanceModuleSeeder extends Seeder
{
    public function run(): void
    {
        // Shift standar
        $shifts = [
            ['name' => 'Shift Reguler (Kantor)', 'start_time' => '08:00:00', 'end_time' => '17:00:00', 'late_tolerance_minutes' => 15, 'early_leave_tolerance_minutes' => 15],
            ['name' => 'Shift Pagi (Toko)', 'start_time' => '07:00:00', 'end_time' => '15:00:00', 'late_tolerance_minutes' => 10, 'early_leave_tolerance_minutes' => 10],
            ['name' => 'Shift Siang (Toko)', 'start_time' => '15:00:00', 'end_time' => '23:00:00', 'late_tolerance_minutes' => 10, 'early_leave_tolerance_minutes' => 10],
            ['name' => 'Shift Malam (Gudang)', 'start_time' => '23:00:00', 'end_time' => '07:00:00', 'late_tolerance_minutes' => 10, 'early_leave_tolerance_minutes' => 10],
        ];

        foreach ($shifts as $shift) {
            WorkShift::firstOrCreate(['name' => $shift['name']], $shift);
        }

        // Weekly off: setiap hari Minggu
        Holiday::firstOrCreate(
            ['type' => HolidayType::WeeklyOff->value, 'weekly_day_of_week' => 0],
            [
                'name' => 'Libur Mingguan',
                'holiday_date' => null,
                'is_recurring_annual' => false,
                'notes' => 'Hari Minggu',
            ],
        );
    }
}
