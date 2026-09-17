<?php

declare(strict_types=1);

namespace App\Enums;

enum MarketplaceOrderStatus: string
{
    case New = 'new';
    case Converted = 'converted';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::Converted => 'Dikonversi',
            self::Shipped => 'Dikirim',
            self::Delivered => 'Diterima',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
