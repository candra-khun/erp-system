<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GatewayTransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Transaksi payment gateway (QRIS/VA/card) — PRD Fase 4 #14.
 */
class GatewayTransaction extends Model
{
    /** @use HasFactory<GatewayTransactionFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transaction_number',
        'gateway_transaction_id',
        'provider',
        'gateway_credential_id',
        'payable_type',
        'payable_id',
        'warehouse_id',
        'payment_channel',
        'payment_reference',
        'amount',
        'fee_amount',
        'settlement_amount',
        'currency',
        'payload_request',
        'payload_response',
        'status',
        'expires_at',
        'paid_at',
        'failure_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'settlement_amount' => 'decimal:2',
            'payload_request' => 'array',
            'payload_response' => 'array',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<GatewayCredential, $this>
     */
    public function credential(): BelongsTo
    {
        return $this->belongsTo(GatewayCredential::class, 'gateway_credential_id');
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Apakah transaksi sudah dibayar (fund settled).
     */
    public function isPaid(): bool
    {
        return in_array($this->status, ['paid', 'settlement'], true);
    }

    /**
     * Apakah transaksi masih bisa dibayar (menunggu pembayaran).
     */
    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'created'], true);
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

        return $query->whereIn('gateway_transactions.warehouse_id', $warehouseIds);
    }
}
