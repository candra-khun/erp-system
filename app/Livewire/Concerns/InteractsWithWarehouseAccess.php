<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Models\Warehouse;
use App\Support\WarehouseAccess;
use Illuminate\Database\Eloquent\Collection;

/**
 * Scoping akses cabang/gudang untuk komponen Livewire (PRD §2: Admin Cabang = 1 cabang).
 *
 * Semantik (lihat App\Support\WarehouseAccess):
 * - super_admin / user tanpa assignment = akses penuh.
 * - user dengan assignment = hanya cabang terkait.
 */
trait InteractsWithWarehouseAccess
{
    /**
     * Opsi gudang untuk dropdown filter/form — hanya cabang dalam scope user.
     *
     * @return Collection<int, Warehouse>
     */
    private function accessibleWarehouseOptions(): Collection
    {
        $ids = $this->accessibleWarehouseIds();

        return Warehouse::when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Nilai filter gudang yang valid: reset ke '' bila di luar scope user.
     */
    private function clampWarehouseFilter(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $user = auth()->user();

        if ($user !== null && WarehouseAccess::canAccess($user, (int) $value)) {
            return $value;
        }

        return '';
    }

    /**
     * Versi int dari clampWarehouseFilter untuk filter bertipe ?int.
     */
    private function clampWarehouseId(?int $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $user = auth()->user();

        if ($user !== null && WarehouseAccess::canAccess($user, $value)) {
            return $value;
        }

        return null;
    }

    /**
     * ID gudang yang boleh diakses user untuk query list ( null = semua cabang ).
     *
     * @return list<int>|null null = tanpa batasan (user tidak di-scope)
     */
    protected function accessibleWarehouseIds(): ?array
    {
        return WarehouseAccess::assignedWarehouseIds(auth()->user());
    }
}
