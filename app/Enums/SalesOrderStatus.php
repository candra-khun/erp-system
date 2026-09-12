<?php

declare(strict_types=1);

namespace App\Enums;

enum SalesOrderStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Confirmed => 'Dikonfirmasi',
            self::Processing => 'Diproses',
            self::Shipped => 'Dikirim',
            self::Delivered => 'Terkirim',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
