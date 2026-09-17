<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GatewayCredentialFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kredensial payment gateway per provider + cabang (PRD Fase 4 #14).
 */
class GatewayCredential extends Model
{
    /** @use HasFactory<GatewayCredentialFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'provider',
        'merchant_id',
        'api_key',
        'server_key',
        'additional_config',
        'warehouse_id',
        'sandbox_mode',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'additional_config' => 'array',
            'sandbox_mode' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<GatewayTransaction>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(GatewayTransaction::class);
    }

    /**
     * Ambil konfigurasi tambahan secara aman.
     */
    public function config(string $key, mixed $default = null): mixed
    {
        return $this->additional_config[$key] ?? $default;
    }

    /**
     * Scope ke cabang yang dapat diakses user (null = tanpa batasan).
     *
     * @param  ?list<int>  $warehouseIds
     */
    public function scopeAccessibleWarehouse(Builder $query, ?array $warehouseIds): Builder
    {
        if ($warehouseIds === null) {
            return $query;
        }

        return $query->whereIn('gateway_credentials.warehouse_id', $warehouseIds);
    }
}
