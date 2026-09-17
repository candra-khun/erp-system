<?php

declare(strict_types=1);

namespace App\Enums;

enum ShipmentStatus: string
{
    case Preparing = 'preparing';
    case Dispatched = 'dispatched';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Preparing => 'Diproses',
            self::Dispatched => 'Dikirim',
            self::InTransit => 'Dalam Perjalanan',
            self::Delivered => 'Diterima',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
