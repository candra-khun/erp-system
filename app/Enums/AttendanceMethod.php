<?php

declare(strict_types=1);

namespace App\Enums;

enum AttendanceMethod: string
{
    case Manual = 'manual';
    case Pin = 'pin';
    case Barcode = 'barcode';
    case Fingerprint = 'fingerprint';
    case Gps = 'gps';
    case Selfie = 'selfie';
    case Web = 'web';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual (Admin)',
            self::Pin => 'PIN',
            self::Barcode => 'Barcode',
            self::Fingerprint => 'Sidik Jari',
            self::Gps => 'GPS',
            self::Selfie => 'Selfie',
            self::Web => 'Web',
        };
    }
}
