<?php

declare(strict_types=1);

namespace App\Enums;

enum ShiftScheduleSource: string
{
    case Manual = 'manual';
    case Recurring = 'recurring';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Recurring => 'Berulang (Mingguan)',
        };
    }
}
