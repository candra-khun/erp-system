<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\WarehouseAccess;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validasi bahwa cabang/gudang yang dipilih berada dalam scope akses user
 * (PRD §2: Admin Cabang mengelola 1 cabang).
 */
class WarehouseAccessible implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = auth()->user();

        if ($user !== null && ! WarehouseAccess::canAccess($user, (int) $value)) {
            $fail('Anda tidak memiliki akses ke cabang/gudang tersebut.');
        }
    }
}
