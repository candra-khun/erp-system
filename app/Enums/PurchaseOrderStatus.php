<?php

declare(strict_types=1);

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case SentToSupplier = 'sent_to_supplier';
    case PartialReceived = 'partial_received';
    case Received = 'received';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Submitted => 'Diajukan',
            self::Approved => 'Disetujui',
            self::SentToSupplier => 'Dikirim ke Supplier',
            self::PartialReceived => 'Diterima Sebagian',
            self::Received => 'Diterima',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
