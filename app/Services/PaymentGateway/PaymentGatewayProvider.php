<?php

declare(strict_types=1);

namespace App\Services\PaymentGateway;

use App\Models\GatewayTransaction;

/**
 * Kontrak untuk provider payment gateway (PRD Fase 4 #14).
 *
 * Setiap provider konkret (Midtrans, Xendit, QRIS via bank, dll) mengimplementasikan
 * kontrak ini sehingga POS/API tidak terikat vendor. Provider "manual" digunakan
 * untuk verifikasi/penyesuaian langsung oleh admin.
 *
 * @phpstan-type ChargeResult array{gateway_transaction_id: ?string, status: string, payment_reference: ?string, payment_channel: ?string, fee_amount: float, payload: ?array}
 */
interface PaymentGatewayProvider
{
    /**
     * Nama unik provider, mis. "midtrans", "xendit", "manual".
     */
    public function name(): string;

    /**
     * Buat charge / QR / virtual account untuk pembayaran.
     *
     * @param  array{amount: float, reference: string, customer?: ?string, channel?: ?string, expires_in_minutes?: int}  $data
     * @return ChargeResult
     */
    public function createCharge(array $data): array;

    /**
     * Cek status transaksi ke provider (sinkron).
     *
     * @return array{status: string, payment_reference: ?string, paid_at: ?string, payload: ?array}
     */
    public function checkStatus(GatewayTransaction $transaction): array;

    /**
     * Batalkan transaksi yang masih pending.
     *
     * @return array{status: string, payload: ?array}
     */
    public function cancel(GatewayTransaction $transaction): array;

    /**
     * Refund transaksi yang sudah dibayar.
     *
     * @return array{status: string, payload: ?array}
     */
    public function refund(GatewayTransaction $transaction, ?float $amount = null): array;
}
