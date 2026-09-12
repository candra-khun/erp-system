<?php

declare(strict_types=1);

namespace App\Enums;

enum PosShiftStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Buka',
            self::Closed => 'Tutup',
        };
    }
}
