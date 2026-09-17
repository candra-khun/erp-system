<?php

declare(strict_types=1);

namespace App\Enums;

enum MarketplaceSyncStatus: string
{
    case Pending = 'pending';
    case Synced = 'synced';
    case Failed = 'failed';
    case Conflict = 'conflict';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Antri',
            self::Synced => 'Tersinkron',
            self::Failed => 'Gagal',
            self::Conflict => 'Konflik',
        };
    }
}
