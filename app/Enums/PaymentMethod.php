<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Transfer = 'transfer';
    case Card = 'card';
    case Split = 'split';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tunai',
            self::Transfer => 'Transfer',
            self::Card => 'Kartu',
            self::Split => 'Campuran',
        };
    }
}
