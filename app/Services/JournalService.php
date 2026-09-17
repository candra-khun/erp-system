<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Support\Facades\DB;

class JournalService
{
    /**
     * Create a balanced journal entry with debit/credit lines.
     *
     * @param array{
     *     journal_date: string,
     *     type: string,
     *     reference_type?: string,
     *     reference_id?: int,
     *     description: string,
     *     created_by?: int,
     *     lines: array<array{account_id: int, type: string, amount: float, description?: string}>
     * } $data
     */
    public function createJournal(array $data): JournalEntry
    {
        return DB::transaction(function () use ($data) {
            $journal = JournalEntry::create([
                'journal_number' => $this->generateJournalNumber(),
                'journal_date' => $data['journal_date'],
                'type' => $data['type'],
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'description' => $data['description'],
                'created_by' => $data['created_by'] ?? null,
                'is_posted' => true,
            ]);

            $totalDebit = 0;
            $totalCredit = 0;

            foreach ($data['lines'] as $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $journal->id,
                    'account_id' => $line['account_id'],
                    'type' => $line['type'],
                    'amount' => $line['amount'],
                    'description' => $line['description'] ?? null,
                ]);

                if ($line['type'] === 'debit') {
                    $totalDebit += $line['amount'];
                } else {
                    $totalCredit += $line['amount'];
                }
            }

            // Validate balanced
            if (abs($totalDebit - $totalCredit) > 0.01) {
                throw new \RuntimeException(
                    "Journal not balanced. Debit: {$totalDebit}, Credit: {$totalCredit}"
                );
            }

            return $journal->load('lines.account');
        });
    }

    /**
     * Create journal for Goods Receipt (penerimaan barang dari PO).
     * Dr: Inventory Asset, Cr: Accounts Payable
     */
    public function createGoodsReceiptJournal(
        int $goodsReceiptId,
        float $totalAmount,
        int $inventoryAccountId,
        int $payableAccountId,
        ?int $userId = null,
        ?string $description = null,
    ): JournalEntry {
        return $this->createJournal([
            'journal_date' => now()->toDateString(),
            'type' => 'purchase',
            'reference_type' => 'goods_receipt',
            'reference_id' => $goodsReceiptId,
            'description' => $description ?? "Jurnal penerimaan barang GR-{$goodsReceiptId}",
            'created_by' => $userId,
            'lines' => [
                ['account_id' => $inventoryAccountId, 'type' => 'debit', 'amount' => $totalAmount, 'description' => 'Persediaan barang'],
                ['account_id' => $payableAccountId, 'type' => 'credit', 'amount' => $totalAmount, 'description' => 'Hutang usaha'],
            ],
        ]);
    }

    /**
     * Create journal for Sales Transaction (penjualan tunai/kredit).
     * Dr: Cash/AR, Cr: Revenue
     * Dr: COGS, Cr: Inventory (if tracking COGS)
     */
    public function createSalesJournal(
        string $referenceType,
        int $referenceId,
        float $revenueAmount,
        int $receivableOrCashAccountId,
        int $revenueAccountId,
        ?float $cogsAmount = null,
        ?int $cogsAccountId = null,
        ?int $inventoryAccountId = null,
        ?int $userId = null,
        ?string $description = null,
    ): JournalEntry {
        $lines = [
            ['account_id' => $receivableOrCashAccountId, 'type' => 'debit', 'amount' => $revenueAmount, 'description' => 'Piutang/Kas dari penjualan'],
            ['account_id' => $revenueAccountId, 'type' => 'credit', 'amount' => $revenueAmount, 'description' => 'Pendapatan penjualan'],
        ];

        // Add COGS lines if provided
        if ($cogsAmount !== null && $cogsAccountId !== null && $inventoryAccountId !== null) {
            $lines[] = ['account_id' => $cogsAccountId, 'type' => 'debit', 'amount' => $cogsAmount, 'description' => 'Harga pokok penjualan'];
            $lines[] = ['account_id' => $inventoryAccountId, 'type' => 'credit', 'amount' => $cogsAmount, 'description' => 'Pengurangan persediaan'];
        }

        return $this->createJournal([
            'journal_date' => now()->toDateString(),
            'type' => 'sales',
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description ?? "Jurnal penjualan {$referenceType}-{$referenceId}",
            'created_by' => $userId,
            'lines' => $lines,
        ]);
    }

    /**
     * Create journal for a POS sale with split settlement.
     *
     * Debits one settlement account per payment method (cash drawer, bank) and
     * credits a single revenue account, optionally with COGS/inventory lines.
     *
     * @param  array<int, array{account_id: int, amount: float, label: string}>  $settlements
     */
    public function createPosSalesJournal(
        int $salesTransactionId,
        array $settlements,
        int $revenueAccountId,
        ?float $cogsAmount = null,
        ?int $cogsAccountId = null,
        ?int $inventoryAccountId = null,
        ?int $userId = null,
        ?string $description = null,
    ): JournalEntry {
        $lines = [];
        $revenueTotal = 0.0;

        foreach ($settlements as $settlement) {
            $amount = (float) $settlement['amount'];
            $revenueTotal += $amount;

            $lines[] = [
                'account_id' => $settlement['account_id'],
                'type' => 'debit',
                'amount' => $amount,
                'description' => $settlement['label'],
            ];
        }

        $lines[] = [
            'account_id' => $revenueAccountId,
            'type' => 'credit',
            'amount' => $revenueTotal,
            'description' => 'Pendapatan penjualan',
        ];

        if ($cogsAmount !== null && $cogsAccountId !== null && $inventoryAccountId !== null) {
            $lines[] = ['account_id' => $cogsAccountId, 'type' => 'debit', 'amount' => $cogsAmount, 'description' => 'Harga pokok penjualan'];
            $lines[] = ['account_id' => $inventoryAccountId, 'type' => 'credit', 'amount' => $cogsAmount, 'description' => 'Pengurangan persediaan'];
        }

        return $this->createJournal([
            'journal_date' => now()->toDateString(),
            'type' => 'sales',
            'reference_type' => 'sales_transaction',
            'reference_id' => $salesTransactionId,
            'description' => $description ?? "Jurnal penjualan POS {$salesTransactionId}",
            'created_by' => $userId,
            'lines' => $lines,
        ]);
    }

    /**
     * Create journal for Purchase Return.
     * Dr: Accounts Payable, Cr: Inventory Asset
     */
    public function createPurchaseReturnJournal(
        int $purchaseReturnId,
        float $totalAmount,
        int $payableAccountId,
        int $inventoryAccountId,
        ?int $userId = null,
    ): JournalEntry {
        return $this->createJournal([
            'journal_date' => now()->toDateString(),
            'type' => 'purchase',
            'reference_type' => 'purchase_return',
            'reference_id' => $purchaseReturnId,
            'description' => "Jurnal retur pembelian PR-{$purchaseReturnId}",
            'created_by' => $userId,
            'lines' => [
                ['account_id' => $payableAccountId, 'type' => 'debit', 'amount' => $totalAmount, 'description' => 'Pengurangan hutang usaha'],
                ['account_id' => $inventoryAccountId, 'type' => 'credit', 'amount' => $totalAmount, 'description' => 'Pengurangan persediaan'],
            ],
        ]);
    }

    /**
     * Create journal for Sales Return.
     * Dr: Revenue, Cr: Cash/AR
     * Dr: Inventory, Cr: COGS (if restocking)
     */
    public function createSalesReturnJournal(
        int $salesReturnId,
        float $totalAmount,
        int $receivableOrCashAccountId,
        int $revenueAccountId,
        ?float $restockAmount = null,
        ?int $inventoryAccountId = null,
        ?int $cogsAccountId = null,
        ?int $userId = null,
    ): JournalEntry {
        $lines = [
            ['account_id' => $revenueAccountId, 'type' => 'debit', 'amount' => $totalAmount, 'description' => 'Pengurangan pendapatan'],
            ['account_id' => $receivableOrCashAccountId, 'type' => 'credit', 'amount' => $totalAmount, 'description' => 'Pengembalian kas/piutang'],
        ];

        if ($restockAmount !== null && $inventoryAccountId !== null && $cogsAccountId !== null) {
            $lines[] = ['account_id' => $inventoryAccountId, 'type' => 'debit', 'amount' => $restockAmount, 'description' => 'Restok persediaan'];
            $lines[] = ['account_id' => $cogsAccountId, 'type' => 'credit', 'amount' => $restockAmount, 'description' => 'Pengurangan HPP'];
        }

        return $this->createJournal([
            'journal_date' => now()->toDateString(),
            'type' => 'sales',
            'reference_type' => 'sales_return',
            'reference_id' => $salesReturnId,
            'description' => "Jurnal retur penjualan SR-{$salesReturnId}",
            'created_by' => $userId,
            'lines' => $lines,
        ]);
    }

    private function generateJournalNumber(): string
    {
        $date = now()->format('Ymd');
        $last = JournalEntry::whereDate('created_at', today())
            ->orderByDesc('id')
            ->value('journal_number');

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last);
            $lastSeq = (int) end($parts);
            $seq = $lastSeq + 1;
        }

        return 'JV-'.$date.'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
