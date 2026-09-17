<?php

declare(strict_types=1);

namespace App\Services\PaymentGateway;

use App\Models\GatewayTransaction;
use RuntimeException;

/**
 * Provider "manual": kasir/admin menandai pembayaran gateway diterima
 * (mis. bukti transfer masuk / konfirmasi QRIS dari mesin fisik).
 *
 * Provider ini TIDAK memanggil API pihak ketiga — aman dipakai tanpa
 * kredensial vendor, dan cocok untuk cabang yang mesin EDC/QRIS-nya
 * direkonsiliasi manual.
 */
final class ManualPaymentProvider implements PaymentGatewayProvider
{
    public function name(): string
    {
        return 'manual';
    }

    public function createCharge(array $data): array
    {
        $amount = (float) ($data['amount'] ?? 0);

        if ($amount <= 0) {
            throw new RuntimeException('Nominal charge gateway harus lebih dari 0.');
        }

        return [
            'gateway_transaction_id' => 'MANUAL-'.strtoupper(uniqid()),
            'status' => 'pending',
            'payment_reference' => $data['reference'] ?? null,
            'payment_channel' => $data['channel'] ?? 'manual',
            'fee_amount' => 0.0,
            'payload' => ['mode' => 'manual', 'note' => 'Konfirmasi pembayaran dilakukan manual oleh admin.'],
        ];
    }

    public function checkStatus(GatewayTransaction $transaction): array
    {
        return [
            'status' => (string) $transaction->status,
            'payment_reference' => $transaction->payment_reference,
            'paid_at' => $transaction->paid_at?->toIso8601String(),
            'payload' => $transaction->payload_response,
        ];
    }

    public function cancel(GatewayTransaction $transaction): array
    {
        if ($transaction->isPaid()) {
            throw new RuntimeException('Transaksi yang sudah dibayar tidak dapat dibatalkan — lakukan refund.');
        }

        return ['status' => 'cancel', 'payload' => ['cancelled_manually' => true]];
    }

    public function refund(GatewayTransaction $transaction, ?float $amount = null): array
    {
        if (! $transaction->isPaid()) {
            throw new RuntimeException('Hanya transaksi yang sudah dibayar yang dapat di-refund.');
        }

        return ['status' => 'refund', 'payload' => ['refunded_manually' => true, 'amount' => $amount ?? (float) $transaction->amount]];
    }
}
