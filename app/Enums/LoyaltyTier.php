<?php

declare(strict_types=1);

namespace App\Enums;

enum LoyaltyTier: string
{
    case Bronze = 'bronze';
    case Silver = 'silver';
    case Gold = 'gold';

    public function label(): string
    {
        return match ($this) {
            self::Bronze => 'Bronze',
            self::Silver => 'Silver',
            self::Gold => 'Gold',
        };
    }

    /**
     * Diskon khusus per tier (persen).
     */
    public function discountPercent(): float
    {
        return match ($this) {
            self::Bronze => 0.0,
            self::Silver => 2.0,
            self::Gold => 5.0,
        };
    }

    /**
     * Ambang poin minimal untuk mencapai tier.
     */
    public function requiredPoints(): int
    {
        return match ($this) {
            self::Bronze => 0,
            self::Silver => 1000,
            self::Gold => 5000,
        };
    }
}
