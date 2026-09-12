<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\JournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalServiceTest extends TestCase
{
    use RefreshDatabase;

    private JournalService $journalService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->journalService = new JournalService;
    }

    public function test_create_balanced_journal(): void
    {
        $debitAccount = Account::factory()->asset()->create();
        $creditAccount = Account::factory()->liability()->create();
        $user = User::factory()->create();

        $journal = $this->journalService->createJournal([
            'journal_date' => now()->toDateString(),
            'type' => 'general',
            'description' => 'Test journal entry',
            'created_by' => $user->id,
            'lines' => [
                ['account_id' => $debitAccount->id, 'type' => 'debit', 'amount' => 1000000, 'description' => 'Debit line'],
                ['account_id' => $creditAccount->id, 'type' => 'credit', 'amount' => 1000000, 'description' => 'Credit line'],
            ],
        ]);

        $this->assertInstanceOf(JournalEntry::class, $journal);
        $this->assertTrue($journal->is_posted);
        $this->assertCount(2, $journal->lines);
        $this->assertTrue($journal->isBalanced());
        $this->assertStringStartsWith('JV-', $journal->journal_number);
    }

    public function test_unbalanced_journal_throws_exception(): void
    {
        $debitAccount = Account::factory()->asset()->create();
        $creditAccount = Account::factory()->liability()->create();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Journal not balanced');

        $this->journalService->createJournal([
            'journal_date' => now()->toDateString(),
            'type' => 'general',
            'description' => 'Unbalanced journal',
            'lines' => [
                ['account_id' => $debitAccount->id, 'type' => 'debit', 'amount' => 1000000],
                ['account_id' => $creditAccount->id, 'type' => 'credit', 'amount' => 500000],
            ],
        ]);
    }

    public function test_goods_receipt_journal(): void
    {
        $inventoryAccount = Account::factory()->asset()->create();
        $payableAccount = Account::factory()->liability()->create();

        $journal = $this->journalService->createGoodsReceiptJournal(
            goodsReceiptId: 1,
            totalAmount: 5000000,
            inventoryAccountId: $inventoryAccount->id,
            payableAccountId: $payableAccount->id,
        );

        $this->assertEquals('purchase', $journal->type);
        $this->assertEquals('goods_receipt', $journal->reference_type);
        $this->assertEquals(1, $journal->reference_id);
        $this->assertCount(2, $journal->lines);

        $debitLine = $journal->lines->firstWhere('type', 'debit');
        $creditLine = $journal->lines->firstWhere('type', 'credit');

        $this->assertEquals($inventoryAccount->id, $debitLine->account_id);
        $this->assertEquals($payableAccount->id, $creditLine->account_id);
        $this->assertEquals(5000000, (float) $debitLine->amount);
        $this->assertEquals(5000000, (float) $creditLine->amount);
    }

    public function test_sales_journal_without_cogs(): void
    {
        $cashAccount = Account::factory()->asset()->create();
        $revenueAccount = Account::factory()->revenue()->create();

        $journal = $this->journalService->createSalesJournal(
            referenceType: 'sales_transaction',
            referenceId: 42,
            revenueAmount: 750000,
            receivableOrCashAccountId: $cashAccount->id,
            revenueAccountId: $revenueAccount->id,
        );

        $this->assertEquals('sales', $journal->type);
        $this->assertCount(2, $journal->lines);
        $this->assertTrue($journal->isBalanced());
    }

    public function test_sales_journal_with_cogs(): void
    {
        $cashAccount = Account::factory()->asset()->create();
        $revenueAccount = Account::factory()->revenue()->create();
        $cogsAccount = Account::factory()->expense()->create();
        $inventoryAccount = Account::factory()->asset()->create();

        $journal = $this->journalService->createSalesJournal(
            referenceType: 'sales_transaction',
            referenceId: 43,
            revenueAmount: 1000000,
            receivableOrCashAccountId: $cashAccount->id,
            revenueAccountId: $revenueAccount->id,
            cogsAmount: 600000,
            cogsAccountId: $cogsAccount->id,
            inventoryAccountId: $inventoryAccount->id,
        );

        $this->assertCount(4, $journal->lines);
        $this->assertTrue($journal->isBalanced());
    }
}
