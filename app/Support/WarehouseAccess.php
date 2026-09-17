<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * Scoping akses user ke cabang/gudang (PRD §2: Admin Cabang mengelola 1 cabang;
 * target 20 cabang, single-entity).
 *
 * Semantik:
 * - super_admin: akses penuh ke semua cabang.
 * - User TANPA assignment: akses penuh (staf pusat / HQ) — backwards-compatible.
 * - User DENGAN assignment: hanya cabang yang di-assign.
 */
class WarehouseAccess
{
    /**
     * True jika user boleh mengakses cabang/gudang tersebut.
     */
    public static function canAccess(User $user, int $warehouseId): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        $assigned = self::assignedWarehouseIds($user);

        return $assigned === null || in_array($warehouseId, $assigned, true);
    }

    /**
     * True jika user dibatasi ke subset cabang tertentu (bukan akses penuh).
     */
    public static function isRestricted(User $user): bool
    {
        return ! $user->hasRole('super_admin') && self::assignedWarehouseIds($user) !== null;
    }

    /**
     * ID cabang yang di-assign ke user.
     *
     * @return list<int>|null null = tidak dibatasi (akses penuh)
     */
    public static function assignedWarehouseIds(?User $user): ?array
    {
        if ($user === null) {
            return [];
        }

        $assigned = $user->warehouses()->pluck('warehouses.id')->all();

        return $assigned === [] ? null : $assigned;
    }
}
