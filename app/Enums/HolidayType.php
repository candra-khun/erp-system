<?php

declare(strict_types=1);

namespace App\Enums;

enum HolidayType: string
{
    case National = 'national';
    case Religious = 'religious';
    case Company = 'company';
    case WeeklyOff = 'weekly_off';

    public function label(): string
    {
        return match ($this) {
            self::National => 'Libur Nasional',
            self::Religious => 'Libur Agama',
            self::Company => 'Libur Perusahaan',
            self::WeeklyOff => 'Libur Mingguan',
        };
    }
}
