<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BankReconciliation;
use App\Models\BankStatementLine;
use App\Models\CashTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Bank reconciliation service (PRD 4.5 — Rekonsiliasi Bank, Could Have).
 * Matches imported bank statement lines against system cash transactions
 * by amount + date window, then computes book vs bank difference.
 */
class BankReconciliationService
{
    /**
     * Create a reconciliation session with its statement lines.
     *
     * @param  array{warehouse_id?: int, period_start: string, period_end: string, bank_statement_balance: float, notes?: string}  $data
     * @param  list<array{value_date: string, description?: string, amount: float, bank_reference?: string}>  $lines
     */
    public function createSession(array $data, array $lines, int $userId): BankReconciliation
    {
        return DB::transaction(function () use ($data, $lines, $userId): BankReconciliation {
            $reconciliation = BankReconciliation::create([
                'reconciliation_number' => $this->generateNumber(),
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'bank_statement_balance' => $data['bank_statement_balance'],
                'status' => 'open',
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($lines as $line) {
                BankStatementLine::create([
                    'bank_reconciliation_id' => $reconciliation->id,
                    'value_date' => $line['value_date'],
                    'description' => $line['description'] ?? '',
                    'amount' => $line['amount'],
                    'bank_reference' => $line['bank_reference'] ?? null,
                ]);
            }

            $this->autoMatch($reconciliation);
            $this->recomputeBalances($reconciliation);

            return $reconciliation->fresh()->load('statementLines');
        });
    }

    /**
     * Auto-match statement lines against cash transactions by exact amount
     * within a ±3 day window of the statement value date.
     */
    public function autoMatch(BankReconciliation $reconciliation): int
    {
        $matched = 0;

        $reconciliation->statementLines()
            ->where('is_matched', false)
            ->orderBy('value_date')
            ->get()
            ->each(function (BankStatementLine $line) use (&$matched, $reconciliation): void {
                $candidate = CashTransaction::whereBetween('transaction_date', [
                    $line->value_date->copy()->subDays(3)->toDateString(),
                    $line->value_date->copy()->addDays(3)->toDateString(),
                ])
                    ->whereRaw('ABS(amount - ?) < 0.01', [(float) $line->amount])
                    ->whereNotIn('id', function ($q) use ($reconciliation): void {
                        $q->select('matched_transaction_id')
                            ->from('bank_statement_lines')
                            ->whereNotNull('matched_transaction_id')
                            ->where('bank_reconciliation_id', $reconciliation->id);
                    })
                    ->orderBy('transaction_date')
                    ->first();

                if ($candidate) {
                    $line->update([
                        'is_matched' => true,
                        'matched_transaction_id' => $candidate->id,
                    ]);
                    $matched++;
                }
            });

        return $matched;
    }

    /**
     * Manually match/unmatch a single statement line.
     */
    public function setMatch(BankStatementLine $line, ?int $cashTransactionId): BankStatementLine
    {
        if ($cashTransactionId !== null) {
            $cash = CashTransaction::findOrFail($cashTransactionId);

            if ((float) $cash->amount !== (float) $line->amount) {
                throw new \RuntimeException('Nominal transaksi tidak sama dengan baris rekening koran.');
            }

            $line->update(['is_matched' => true, 'matched_transaction_id' => $cash->id]);
        } else {
            $line->update(['is_matched' => false, 'matched_transaction_id' => null]);
        }

        return $line->fresh();
    }

    /**
     * Recompute book balance (matched cash transactions in period) and difference.
     */
    public function recomputeBalances(BankReconciliation $reconciliation): BankReconciliation
    {
        $matchedIds = $reconciliation->statementLines()
            ->where('is_matched', true)
            ->whereNotNull('matched_transaction_id')
            ->pluck('matched_transaction_id');

        $bookBalance = (float) CashTransaction::whereIn('id', $matchedIds)
            ->whereBetween('transaction_date', [$reconciliation->period_start, $reconciliation->period_end])
            ->sum('amount');

        $reconciliation->update([
            'book_balance' => $bookBalance,
            'difference' => round((float) $reconciliation->bank_statement_balance - $bookBalance, 2),
        ]);

        return $reconciliation->fresh();
    }

    /**
     * Complete the reconciliation. Requires every line to be matched.
     *
     * @throws \RuntimeException when unmatched lines remain
     */
    public function complete(BankReconciliation $reconciliation, int $userId): BankReconciliation
    {
        if ($reconciliation->status !== 'open') {
            throw new \RuntimeException('Sesi rekonsiliasi ini sudah selesai/dibatalkan.');
        }

        $unmatched = $reconciliation->statementLines()->where('is_matched', false)->count();

        if ($unmatched > 0) {
            throw new \RuntimeException("Masih ada {$unmatched} baris belum dicocokkan.");
        }

        $reconciliation = $this->recomputeBalances($reconciliation);
        $reconciliation->update([
            'status' => 'completed',
            'completed_by' => $userId,
            'completed_at' => now(),
        ]);

        return $reconciliation->fresh();
    }

    private function generateNumber(): string
    {
        $datePrefix = 'BR-'.now()->format('Ymd').'-';

        $latest = BankReconciliation::where('reconciliation_number', 'like', $datePrefix.'%')
            ->orderByDesc('reconciliation_number')
            ->value('reconciliation_number');

        $sequence = $latest !== null ? ((int) substr($latest, -4)) + 1 : 1;

        return $datePrefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
