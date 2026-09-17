<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\CashTransaction;
use App\Models\SalesTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Central finance operations service (PRD 4.5).
 * Handles AP/AR payments with cash transaction + automatic journal posting,
 * so UI and API share one consistent code path.
 */
class FinanceService
{
    public function __construct(private readonly JournalService $journalService) {}

    /**
     * Record POS sale settlement: cash/bank transactions + sales journal
     * (revenue & COGS) for a completed sales transaction. Called inside the
     * POS checkout DB transaction.
     *
     * Non-cash settlements (transfer/card) are posted to the bank account
     * instead of the cash drawer, and split payments produce one settlement
     * line per method (PRD 4.4).
     *
     * @param  array<int, array{method: string, amount: float}>  $payments  Net settled amount per method, summing to the transaction total
     */
    public function recordPosSale(SalesTransaction $transaction, array $payments, ?int $userId = null): void
    {
        $payments = array_values(array_filter(
            $payments,
            static fn (array $payment): bool => (float) $payment['amount'] > 0.001,
        ));

        if ($payments === []) {
            return;
        }

        foreach ($payments as $payment) {
            CashTransaction::create([
                'transaction_number' => $this->generateCashNumber(),
                'type' => 'in',
                'category' => 'sales',
                'amount' => (float) $payment['amount'],
                'transaction_date' => now()->toDateString(),
                'description' => 'Penjualan POS '.$transaction->transaction_number,
                'warehouse_id' => $transaction->warehouse_id,
                'payment_method' => $payment['method'],
                'created_by' => $userId,
            ]);
        }

        $revenueAccount = Account::where('code', '4110')->first();   // Penjualan Tunai
        $inventoryAccount = Account::where('code', '1310')->first(); // Persediaan
        $cogsAccount = Account::where('code', '5100')->first();      // HPP

        if (! $revenueAccount) {
            return; // CoA belum di-seed — kas tetap tercatat, jurnal dilewati
        }

        $settlements = [];
        foreach ($payments as $payment) {
            $account = $this->settlementAccount($payment['method']);

            if (! $account) {
                return; // akun penampung belum ada — jurnal dilewati agar tetap balance
            }

            $settlements[] = [
                'account_id' => $account->id,
                'amount' => (float) $payment['amount'],
                'label' => 'Penerimaan '.$payment['method'].' penjualan POS',
            ];
        }

        $cogs = ($inventoryAccount && $cogsAccount) ? $this->calculatePosCogs($transaction) : 0.0;

        try {
            $this->journalService->createPosSalesJournal(
                salesTransactionId: (int) $transaction->id,
                settlements: $settlements,
                revenueAccountId: $revenueAccount->id,
                cogsAmount: $cogs > 0 ? $cogs : null,
                cogsAccountId: $cogsAccount?->id,
                inventoryAccountId: $inventoryAccount?->id,
                userId: $userId,
                description: 'Jurnal penjualan POS '.$transaction->transaction_number,
            );
        } catch (\RuntimeException) {
            // Journal unbalanced — skip silently, cash & stock stay consistent.
        }
    }

    /**
     * Akun penampung kas/bank untuk sebuah metode pembayaran POS.
     */
    private function settlementAccount(string $method): ?Account
    {
        $code = match ($method) {
            'transfer', 'card' => '1120', // Bank BCA — settlement transfer & kartu
            default => '1110',            // Kas Toko
        };

        return Account::where('code', $code)->first();
    }

    /**
     * Hitung COGS per item dari moving-average unit cost GRN (fallback: harga beli master).
     */
    private function calculatePosCogs(SalesTransaction $transaction): float
    {
        $cogs = 0.0;

        foreach ($transaction->items as $item) {
            $avgUnitCost = (float) DB::table('goods_receipt_items as gri')
                ->join('goods_receipts as gr', 'gri.goods_receipt_id', '=', 'gr.id')
                ->join('purchase_order_items as poi', 'gri.purchase_order_item_id', '=', 'poi.id')
                ->where('gri.product_id', $item->product_id)
                ->avg('poi.unit_price');

            if ($avgUnitCost <= 0) {
                $avgUnitCost = (float) DB::table('products')
                    ->where('id', $item->product_id)
                    ->value('purchase_price');
            }

            $cogs += $avgUnitCost * (float) $item->quantity;
        }

        return $cogs;
    }

    /**
     * Record a payment against a supplier payable (AP).
     * Creates: CashTransaction(out) + journal Dr: Hutang Supplier / Cr: Kas Toko.
     *
     * @return array{payable: AccountPayable, cash: CashTransaction}
     *
     * @throws \RuntimeException when payment exceeds remaining balance or payable is settled
     */
    public function payPayable(AccountPayable $payable, float $amount, ?int $userId = null, ?string $method = null): array
    {
        return DB::transaction(function () use ($payable, $amount, $userId, $method) {
            $payable = AccountPayable::whereKey($payable->getKey())->lockForUpdate()->firstOrFail();

            if ($payable->status === 'paid') {
                throw new \RuntimeException('Hutang ini sudah lunas.');
            }

            $remaining = (float) $payable->remaining_amount;
            if ($amount <= 0) {
                throw new \RuntimeException('Jumlah pembayaran harus lebih dari 0.');
            }
            if ($amount > $remaining + 0.001) {
                throw new \RuntimeException(
                    'Jumlah pembayaran melebihi sisa hutang ('.number_format($remaining, 2, ',', '.').').'
                );
            }

            $newPaid = (float) $payable->paid_amount + $amount;
            $newRemaining = round((float) $payable->total_amount - $newPaid, 2);
            $status = $newRemaining <= 0.001 ? 'paid' : 'partial';

            $payable->update([
                'paid_amount' => $newPaid,
                'remaining_amount' => max(0, $newRemaining),
                'status' => $status,
            ]);

            $cash = CashTransaction::create([
                'transaction_number' => $this->generateCashNumber(),
                'type' => 'out',
                'category' => 'purchase',
                'amount' => $amount,
                'transaction_date' => now()->toDateString(),
                'description' => 'Pembayaran hutang '.$payable->ap_number,
                'warehouse_id' => $payable->warehouse_id,
                'payment_method' => $method ?? 'transfer',
                'created_by' => $userId,
            ]);

            $this->postPaymentJournal(
                debitCode: '2110',   // Hutang Supplier
                creditCode: '1110',  // Kas Toko
                amount: $amount,
                type: 'purchase',
                referenceType: 'account_payable',
                referenceId: (int) $payable->id,
                description: 'Pembayaran hutang '.$payable->ap_number,
                userId: $userId,
            );

            return ['payable' => $payable->fresh(), 'cash' => $cash];
        });
    }

    /**
     * Record a payment received against a customer receivable (AR).
     * Creates: CashTransaction(in) + journal Dr: Kas Toko / Cr: Piutang Pelanggan.
     *
     * @return array{receivable: AccountReceivable, cash: CashTransaction}
     *
     * @throws \RuntimeException when payment exceeds remaining balance or receivable is settled
     */
    public function collectReceivable(AccountReceivable $receivable, float $amount, ?int $userId = null, ?string $method = null): array
    {
        return DB::transaction(function () use ($receivable, $amount, $userId, $method) {
            $receivable = AccountReceivable::whereKey($receivable->getKey())->lockForUpdate()->firstOrFail();

            if ($receivable->status === 'paid') {
                throw new \RuntimeException('Piutang ini sudah lunas.');
            }

            $remaining = (float) $receivable->remaining_amount;
            if ($amount <= 0) {
                throw new \RuntimeException('Jumlah pembayaran harus lebih dari 0.');
            }
            if ($amount > $remaining + 0.001) {
                throw new \RuntimeException(
                    'Jumlah pembayaran melebihi sisa piutang ('.number_format($remaining, 2, ',', '.').').'
                );
            }

            $newPaid = (float) $receivable->paid_amount + $amount;
            $newRemaining = round((float) $receivable->total_amount - $newPaid, 2);
            $status = $newRemaining <= 0.001 ? 'paid' : 'partial';

            $receivable->update([
                'paid_amount' => $newPaid,
                'remaining_amount' => max(0, $newRemaining),
                'status' => $status,
            ]);

            $cash = CashTransaction::create([
                'transaction_number' => $this->generateCashNumber(),
                'type' => 'in',
                'category' => 'sales',
                'amount' => $amount,
                'transaction_date' => now()->toDateString(),
                'description' => 'Penerimaan piutang '.$receivable->ar_number,
                'warehouse_id' => $receivable->warehouse_id,
                'payment_method' => $method ?? 'transfer',
                'created_by' => $userId,
            ]);

            $this->postPaymentJournal(
                debitCode: '1110',   // Kas Toko
                creditCode: '1210',  // Piutang Pelanggan
                amount: $amount,
                type: 'sales',
                referenceType: 'account_receivable',
                referenceId: (int) $receivable->id,
                description: 'Penerimaan piutang '.$receivable->ar_number,
                userId: $userId,
            );

            return ['receivable' => $receivable->fresh(), 'cash' => $cash];
        });
    }

    /**
     * Post a simple two-line payment journal if both accounts exist.
     */
    private function postPaymentJournal(
        string $debitCode,
        string $creditCode,
        float $amount,
        string $type,
        string $referenceType,
        int $referenceId,
        string $description,
        ?int $userId,
    ): void {
        $debitAccount = Account::where('code', $debitCode)->first();
        $creditAccount = Account::where('code', $creditCode)->first();

        if (! $debitAccount || ! $creditAccount) {
            return; // CoA not seeded — journal silently skipped, cash/AP/AR stays consistent
        }

        $this->journalService->createJournal([
            'journal_date' => now()->toDateString(),
            'type' => $type,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description,
            'created_by' => $userId,
            'lines' => [
                ['account_id' => $debitAccount->id, 'type' => 'debit', 'amount' => $amount, 'description' => $description],
                ['account_id' => $creditAccount->id, 'type' => 'credit', 'amount' => $amount, 'description' => $description],
            ],
        ]);
    }

    private function generateCashNumber(): string
    {
        $datePrefix = 'CT-'.now()->format('Ymd').'-';

        $latest = CashTransaction::where('transaction_number', 'like', $datePrefix.'%')
            ->orderByDesc('transaction_number')
            ->value('transaction_number');

        $sequence = 1;

        if ($latest !== null) {
            $lastSequence = (int) substr($latest, -4);
            $sequence = $lastSequence + 1;
        }

        return $datePrefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
