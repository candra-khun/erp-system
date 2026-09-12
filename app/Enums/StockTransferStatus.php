<?php

declare(strict_types=1);

namespace App\Enums;

enum StockTransferStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case InTransit = 'in_transit';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::PendingApproval => 'Menunggu Persetujuan',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::InTransit => 'Dalam Perjalanan',
            self::Received => 'Diterima',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
