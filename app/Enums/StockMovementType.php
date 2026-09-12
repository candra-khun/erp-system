<?php

declare(strict_types=1);

namespace App\Enums;

enum StockMovementType: string
{
    case In = 'in';
    case Out = 'out';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Adjustment = 'adjustment';
    case Return = 'return';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Masuk',
            self::Out => 'Keluar',
            self::TransferIn => 'Transfer Masuk',
            self::TransferOut => 'Transfer Keluar',
            self::Adjustment => 'Penyesuaian',
            self::Return => 'Retur',
        };
    }
}
