<?php

declare(strict_types=1);

namespace App\Services\PaymentGateway;

use App\Models\GatewayCredential;
use App\Models\GatewayTransaction;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Manager;
use RuntimeException;

/**
 * Manager payment gateway (PRD Fase 4 #14).
 *
 * Menyediakan satu pintu untuk membuat & memproses transaksi gateway
 * terlepas dari provider-nya. Provider didaftarkan sekali di boot;
 * kredensial diambil per cabang (gateway_credentials).
 *
 * @extends Manager<PaymentGatewayProvider>
 */
class PaymentGatewayManager extends Manager
{
    /**
     * Daftarkan provider bawaan (tanpa vendor pihak ketiga).
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->extend('manual', fn (): PaymentGatewayProvider => new ManualPaymentProvider);
    }

    /**
     * Provider default (dipakai saat tidak dispesifikasi).
     */
    public function getDefaultDriver(): string
    {
        return 'manual';
    }

    /**
     * Kredensial aktif untuk provider + cabang tertentu.
     *
     * @throws RuntimeException bila tidak ada kredensial aktif.
     */
    public function credential(string $provider, ?int $warehouseId): GatewayCredential
    {
        $credential = GatewayCredential::where('provider', $provider)
            ->where('is_active', true)
            ->accessibleWarehouse($warehouseId === null ? null : [$warehouseId])
            ->orderByDesc('id')
            ->first();

        if (! $credential) {
            throw new RuntimeException('Kredensial gateway "'.$provider.'" belum dikonfigurasi untuk cabang ini.');
        }

        return $credential;
    }

    /**
     * Buat transaksi gateway baru + charge ke provider.
     *
     * @param  array{provider?: string, amount: float, channel?: ?string, reference: string, payable?: ?Model, warehouse_id?: ?int, user_id?: ?int, expires_in_minutes?: int}  $data
     */
    public function createTransaction(array $data): GatewayTransaction
    {
        $providerName = $data['provider'] ?? $this->getDefaultDriver();
        $provider = $this->driver($providerName);
        $warehouseId = $data['warehouse_id'] ?? null;

        $credential = GatewayCredential::where('provider', $providerName)
            ->where('is_active', true)
            ->accessibleWarehouse($warehouseId === null ? null : [$warehouseId])
            ->orderByDesc('id')
            ->first();

        if (! $credential) {
            // Provider manual tidak wajib kredensial; lainnya wajib.
            if ($providerName !== 'manual') {
                throw new RuntimeException('Kredensial gateway "'.$providerName.'" belum dikonfigurasi untuk cabang ini.');
            }
        }

        $amount = (float) $data['amount'];

        return DB::transaction(function () use ($data, $provider, $providerName, $credential, $warehouseId, $amount): GatewayTransaction {
            $payable = $data['payable'] ?? null;

            $transaction = GatewayTransaction::create([
                'transaction_number' => $this->generateTransactionNumber(),
                'provider' => $providerName,
                'gateway_credential_id' => $credential?->id,
                'payable_type' => $payable ? $payable::class : null,
                'payable_id' => $payable?->id,
                'warehouse_id' => $warehouseId,
                'amount' => $amount,
                'fee_amount' => 0,
                'settlement_amount' => 0,
                'payment_channel' => $data['channel'] ?? null,
                'status' => 'pending',
                'expires_at' => isset($data['expires_in_minutes'])
                    ? now()->addMinutes((int) $data['expires_in_minutes'])
                    : now()->addMinutes(30),
                'created_by' => $data['user_id'] ?? null,
            ]);

            try {
                $result = $provider->createCharge([
                    'amount' => $amount,
                    'reference' => $transaction->transaction_number,
                    'channel' => $data['channel'] ?? null,
                    'expires_in_minutes' => $data['expires_in_minutes'] ?? 30,
                ]);

                $transaction->update([
                    'gateway_transaction_id' => $result['gateway_transaction_id'] ?? null,
                    'payment_reference' => $result['payment_reference'] ?? null,
                    'payment_channel' => $result['payment_channel'] ?? null,
                    'fee_amount' => (float) ($result['fee_amount'] ?? 0),
                    'payload_response' => $result['payload'] ?? null,
                    'status' => $result['status'] ?? 'created',
                ]);
            } catch (RuntimeException $e) {
                $transaction->update([
                    'status' => 'failed',
                    'failure_reason' => $e->getMessage(),
                ]);

                throw $e;
            }

            return $transaction->fresh();
        });
    }

    /**
     * Tandai transaksi telah dibayar (dari webhook atau konfirmasi manual).
     *
     * @param  ?string  $paymentReference  Referensi pembayaran dari provider (mis. QR id).
     */
    public function markPaid(GatewayTransaction $transaction, ?string $paymentReference = null, ?array $payload = null): GatewayTransaction
    {
        return DB::transaction(function () use ($transaction, $paymentReference, $payload): GatewayTransaction {
            $transaction = GatewayTransaction::whereKey($transaction->getKey())->lockForUpdate()->firstOrFail();

            if ($transaction->isPaid()) {
                return $transaction; // idempoten
            }

            $amount = (float) $transaction->amount;
            $fee = (float) $transaction->fee_amount;

            $transaction->update([
                'status' => 'settlement',
                'paid_at' => now(),
                'payment_reference' => $paymentReference ?? $transaction->payment_reference,
                'settlement_amount' => round($amount - $fee, 2),
                'payload_response' => $payload ?? $transaction->payload_response,
            ]);

            return $transaction->fresh();
        });
    }

    /**
     * Sinkronkan status transaksi ke provider (bila mendukung checkStatus).
     */
    public function syncStatus(GatewayTransaction $transaction): GatewayTransaction
    {
        $provider = $this->driver($transaction->provider);

        $result = $provider->checkStatus($transaction);

        $status = (string) ($result['status'] ?? $transaction->status);

        if ($status !== $transaction->status) {
            $transaction->update([
                'status' => $status,
                'payload_response' => $result['payload'] ?? $transaction->payload_response,
            ]);

            if ($status === 'paid' || $status === 'settlement') {
                return $this->markPaid($transaction, $result['payment_reference'] ?? null);
            }
        }

        return $transaction->fresh();
    }

    /**
     * Batalkan transaksi yang masih pending.
     */
    public function cancel(GatewayTransaction $transaction): GatewayTransaction
    {
        $provider = $this->driver($transaction->provider);

        $result = $provider->cancel($transaction);

        $transaction->update([
            'status' => 'cancel',
            'payload_response' => $result['payload'] ?? $transaction->payload_response,
        ]);

        return $transaction->fresh();
    }

    /**
     * Refund transaksi yang sudah dibayar.
     */
    public function refund(GatewayTransaction $transaction, ?float $amount = null): GatewayTransaction
    {
        $provider = $this->driver($transaction->provider);

        $result = $provider->refund($transaction, $amount);

        $transaction->update([
            'status' => 'refund',
            'payload_response' => $result['payload'] ?? $transaction->payload_response,
        ]);

        return $transaction->fresh();
    }

    private function generateTransactionNumber(): string
    {
        $prefix = 'GT-'.now()->format('Ymd').'-';

        $latest = GatewayTransaction::where('transaction_number', 'like', $prefix.'%')
            ->orderByDesc('transaction_number')
            ->value('transaction_number');

        $sequence = 1;

        if ($latest !== null) {
            $sequence = (int) substr($latest, -4) + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
