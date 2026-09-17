<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Concerns;

use App\Models\User;
use App\Support\WarehouseAccess;

/**
 * Scoping akses cabang/gudang untuk endpoint API (PRD §2: Admin Cabang = 1 cabang).
 *
 * Semantik mengikuti App\Support\WarehouseAccess:
 * - super_admin / user tanpa assignment = akses penuh (null).
 * - user dengan assignment = hanya cabang terkait.
 */
trait ScopesWarehouseAccess
{
    /**
     * Scope gudang untuk query laporan.
     *
     * @return list<int>|null null = tanpa batasan (user tidak di-scope)
     */
    protected function resolveWarehouseScope(?int $requestedWarehouseId): ?array
    {
        if ($requestedWarehouseId !== null) {
            return [(int) $requestedWarehouseId];
        }

        return WarehouseAccess::assignedWarehouseIds($this->user());
    }

    /**
     * ID cabang yang boleh diakses user untuk query list.
     *
     * @return list<int>|null null = tanpa batasan (user tidak di-scope)
     */
    protected function accessibleWarehouseIds(): ?array
    {
        return WarehouseAccess::assignedWarehouseIds($this->user());
    }

    /**
     * User terautentikasi untuk keperluan scoping ( Sanctum / session guard ).
     */
    protected function user(): ?User
    {
        return auth()->user();
    }
}
