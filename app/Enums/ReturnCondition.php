<?php

declare(strict_types=1);

namespace App\Enums;

enum ReturnCondition: string
{
    case Good = 'good';
    case Damaged = 'damaged';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Baik',
            self::Damaged => 'Rusak',
            self::Expired => 'Kedaluwarsa',
        };
    }
}
