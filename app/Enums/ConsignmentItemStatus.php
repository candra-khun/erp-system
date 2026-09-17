<?php

declare(strict_types=1);

namespace App\Enums;

enum ConsignmentItemStatus: string
{
    case Active = 'active';
    case SoldOut = 'sold_out';
    case Returned = 'returned';
    case Settled = 'settled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::SoldOut => 'Habis Terjual',
            self::Returned => 'Dikembalikan',
            self::Settled => 'Disettle',
        };
    }
}
