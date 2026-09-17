<?php

declare(strict_types=1);

namespace App\Enums;

enum PayrollComponentType: string
{
    case Earning = 'earning';
    case Deduction = 'deduction';
    case Tax = 'tax';

    public function label(): string
    {
        return match ($this) {
            self::Earning => 'Pendapatan',
            self::Deduction => 'Potongan',
            self::Tax => 'Pajak',
        };
    }
}
