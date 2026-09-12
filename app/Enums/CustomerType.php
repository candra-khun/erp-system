<?php

declare(strict_types=1);

namespace App\Enums;

enum CustomerType: string
{
    case General = 'general';
    case Member = 'member';
    case Reseller = 'reseller';

    public function label(): string
    {
        return match ($this) {
            self::General => 'Umum',
            self::Member => 'Member',
            self::Reseller => 'Reseller',
        };
    }
}
