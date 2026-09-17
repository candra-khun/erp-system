<?php

namespace Tests\Feature;

use App\Livewire\BalanceSheetReport;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Role;
use App\Models\User;
use App\Services\ReportService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BalanceSheetReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        $this->admin = User::factory()->create();
        $superAdmin = Role::where('name', 'super_admin')->firstOrFail();
        $this->admin->roles()->attach($superAdmin);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: float}>  $lines  [account code, type, amount]
     */
    private function postJournal(array $lines, ?string $date = null, bool $posted = true): JournalEntry
    {
        $entry = JournalEntry::create([
            'journal_number' => 'JE-'.uniqid(),
            'journal_date' => $date ?? now()->toDateString(),
            'description' => 'Test entry',
            'is_posted' => $posted,
        ]);

        foreach ($lines as [$code, $type, $amount]) {
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => Account::where('code', $code)->value('id'),
                'type' => $type,
                'amount' => $amount,
            ]);
        }

        return $entry;
    }

    public function test_balance_sheet_page_renders(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reports.balance-sheet'))
            ->assertOk()
            ->assertSee('Neraca');
    }

    public function test_balance_sheet_route_requires_dashboard_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('reports.balance-sheet'))
            ->assertForbidden();
    }

    public function test_balance_sheet_totals_are_balanced_after_cash_sale(): void
    {
        // Dr Kas 1110 / Cr Penjualan 4110
        $this->postJournal([
            ['1110', 'debit', 500000],
            ['4110', 'credit', 500000],
        ]);

        Livewire::actingAs($this->admin)
            ->test(BalanceSheetReport::class)
            ->assertSet('asOfDate', now()->toDateString())
            ->assertViewHas('report', function (array $report): bool {
                return abs($report['total_assets'] - $report['total_liabilities_and_equity']) < 0.01
                    && (float) $report['total_assets'] === 500000.0
                    && (float) $report['retained_earnings'] === 500000.0;
            });
    }

    public function test_balance_sheet_lists_asset_account_with_code(): void
    {
        $this->postJournal([
            ['1110', 'debit', 250000],
            ['4110', 'credit', 250000],
        ]);

        Livewire::actingAs($this->admin)
            ->test(BalanceSheetReport::class)
            ->assertSee('1110')
            ->assertSee('Kas Toko');
    }

    public function test_balance_sheet_excludes_future_entries(): void
    {
        $this->postJournal([
            ['1110', 'debit', 100000],
            ['4110', 'credit', 100000],
        ], now()->addMonth()->toDateString());

        Livewire::actingAs($this->admin)
            ->test(BalanceSheetReport::class)
            ->assertViewHas('report', function (array $report): bool {
                return (float) $report['total_assets'] === 0.0;
            });
    }

    public function test_balance_sheet_excludes_unposted_entries(): void
    {
        $this->postJournal([
            ['1110', 'debit', 999],
            ['4110', 'credit', 999],
        ], posted: false);

        Livewire::actingAs($this->admin)
            ->test(BalanceSheetReport::class)
            ->assertViewHas('report', function (array $report): bool {
                return (float) $report['total_assets'] === 0.0;
            });
    }

    public function test_balance_sheet_pdf_view_renders_totals(): void
    {
        $this->postJournal([
            ['1110', 'debit', 500000],
            ['4110', 'credit', 500000],
        ]);

        $report = app(ReportService::class)->getBalanceSheet(now()->toDateString());

        $html = view('reports.balance-sheet-pdf', [
            'asOfDate' => $report['as_of'],
            'assets' => $report['assets'],
            'liabilities' => $report['liabilities'],
            'equity' => $report['equity'],
            'totalAssets' => $report['total_assets'],
            'totalLiabilities' => $report['total_liabilities'],
            'totalEquityBeforeClosing' => $report['total_equity_before_closing'],
            'retainedEarnings' => $report['retained_earnings'],
            'totalEquity' => $report['total_equity'],
            'totalLiabilitiesAndEquity' => $report['total_liabilities_and_equity'],
        ])->render();

        $this->assertStringContainsString('NERACA', $html);
        $this->assertStringContainsString('TOTAL ASET', $html);
        $this->assertStringContainsString('500.000,00', $html);
    }
}
