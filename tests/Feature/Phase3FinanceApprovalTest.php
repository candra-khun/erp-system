<?php

namespace Tests\Feature;

use App\Models\CashTransaction;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\BankReconciliationService;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase3FinanceApprovalTest extends TestCase
{
    use RefreshDatabase;

    // === Rekonsiliasi Bank ===

    public function test_auto_match_and_complete_reconciliation(): void
    {
        $user = User::factory()->create();
        $today = now()->toDateString();

        // Transaksi kas sistem (2 match, 1 tidak ada di statement)
        $cash1 = CashTransaction::factory()->create(['amount' => 500000, 'transaction_date' => $today]);
        $cash2 = CashTransaction::factory()->create(['amount' => 250000, 'transaction_date' => $today]);
        CashTransaction::factory()->create(['amount' => 99000, 'transaction_date' => $today]);

        $service = app(BankReconciliationService::class);

        $session = $service->createSession(
            [
                'period_start' => now()->subDays(10)->toDateString(),
                'period_end' => $today,
                'bank_statement_balance' => 750000,
            ],
            [
                ['value_date' => $today, 'description' => 'Setoran', 'amount' => 500000],
                ['value_date' => $today, 'description' => 'Transfer masuk', 'amount' => 250000],
            ],
            $user->id,
        );

        // Baris otomatis cocok dengan cash1/cash2
        $this->assertSame(2, $session->statementLines->where('is_matched', true)->count());
        $this->assertEquals(750000, (float) $session->book_balance);
        $this->assertEquals(0, (float) $session->difference);

        $completed = $service->complete($session, $user->id);
        $this->assertSame('completed', $completed->status);
        $this->assertNotNull($completed->completed_at);
    }

    public function test_manual_match_validates_amount(): void
    {
        $user = User::factory()->create();
        $cash = CashTransaction::factory()->create(['amount' => 100000, 'transaction_date' => now()->toDateString()]);

        $service = app(BankReconciliationService::class);
        $session = $service->createSession(
            ['period_start' => now()->toDateString(), 'period_end' => now()->toDateString(), 'bank_statement_balance' => 50],
            [['value_date' => now()->toDateString(), 'description' => 'Bank', 'amount' => 50]],
            $user->id,
        );

        $line = $session->statementLines->first();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tidak sama');
        $service->setMatch($line, $cash->id);
    }

    public function test_complete_requires_all_lines_matched(): void
    {
        $user = User::factory()->create();
        $service = app(BankReconciliationService::class);

        $session = $service->createSession(
            ['period_start' => now()->toDateString(), 'period_end' => now()->toDateString(), 'bank_statement_balance' => 123],
            [['value_date' => now()->toDateString(), 'description' => 'Tidak ada match', 'amount' => 123]],
            $user->id,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('belum dicocokkan');
        $service->complete($session, $user->id);
    }

    // === Approval PO Berjenjang ===

    public function test_po_below_threshold_approved_in_one_step(): void
    {
        $user1 = User::factory()->create();
        $po = PurchaseOrder::factory()->create(['status' => 'submitted', 'total_amount' => 10000000]);
        config(['erp.po_approval.level1_threshold' => 50000000]);

        $result = app(PurchaseOrderService::class)->approve($po, $user1->id);

        $this->assertSame('approved', $result->status);
        $this->assertSame(1, $result->approval_level);
        $this->assertNull($result->second_approved_by);
    }

    public function test_po_above_threshold_needs_two_distinct_approvers(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $po = PurchaseOrder::factory()->create(['status' => 'submitted', 'total_amount' => 150000000]);
        config(['erp.po_approval.level1_threshold' => 50000000]);

        $service = app(PurchaseOrderService::class);

        // Level 1: masuk pending_level2, belum approved
        $result = $service->approve($po, $user1->id);
        $this->assertSame('pending_level2', $result->status);
        $this->assertSame(1, $result->approval_level);

        // Approver yang sama ditolak
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('berbeda');
        $service->approve($result, $user1->id);
    }

    public function test_po_above_threshold_second_approval_completes(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $po = PurchaseOrder::factory()->create(['status' => 'submitted', 'total_amount' => 150000000]);
        config(['erp.po_approval.level1_threshold' => 50000000]);

        $service = app(PurchaseOrderService::class);
        $pending = $service->approve($po, $user1->id);
        $approved = $service->approve($pending, $user2->id);

        $this->assertSame('approved', $approved->status);
        $this->assertSame(2, $approved->approval_level);
        $this->assertSame($user2->id, $approved->second_approved_by);
        $this->assertNotNull($approved->second_approved_at);
    }
}
