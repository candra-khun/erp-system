<?php

namespace Tests\Feature;

use App\Livewire\PosTerminal;
use App\Livewire\ProfitLossReport;
use App\Models\Account;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesTransaction;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\FinanceService;
use App\Services\ReportService;
use App\Services\SalesOrderService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FinanceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $financeUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, ChartOfAccountsSeeder::class]);

        $this->financeUser = User::factory()->create();
        $role = Role::where('name', 'staff_keuangan')->first();
        $this->financeUser->roles()->attach($role);
    }

    private function payable(float $amount): AccountPayable
    {
        $supplier = Supplier::factory()->create();

        return AccountPayable::create([
            'ap_number' => 'AP-TEST-0001',
            'supplier_id' => $supplier->id,
            'total_amount' => $amount,
            'paid_amount' => 0,
            'remaining_amount' => $amount,
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'open',
        ]);
    }

    private function receivable(float $amount): AccountReceivable
    {
        $customer = Customer::factory()->create();

        return AccountReceivable::create([
            'ar_number' => 'AR-TEST-0001',
            'customer_id' => $customer->id,
            'total_amount' => $amount,
            'paid_amount' => 0,
            'remaining_amount' => $amount,
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'open',
        ]);
    }

    public function test_pay_payable_creates_cash_out_and_journal(): void
    {
        $ap = $this->payable(100000);
        $service = app(FinanceService::class);

        $result = $service->payPayable($ap, 40000, $this->financeUser->id);

        $this->assertSame('partial', $result['payable']->status);
        $this->assertEquals(60000, (float) $result['payable']->remaining_amount);

        // Cash out recorded
        $this->assertDatabaseHas('cash_transactions', [
            'type' => 'out',
            'category' => 'purchase',
            'amount' => 40000,
        ]);

        // Journal: Dr Hutang Supplier (2110) / Cr Kas Toko (1110)
        $payableAccount = Account::where('code', '2110')->first();
        $cashAccount = Account::where('code', '1110')->first();

        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => $payableAccount->id,
            'type' => 'debit',
            'amount' => 40000,
        ]);
        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => $cashAccount->id,
            'type' => 'credit',
            'amount' => 40000,
        ]);
    }

    public function test_full_pay_marks_paid(): void
    {
        $ap = $this->payable(100000);
        $service = app(FinanceService::class);

        $result = $service->payPayable($ap, 100000, $this->financeUser->id);

        $this->assertSame('paid', $result['payable']->status);
        $this->assertEquals(0, (float) $result['payable']->remaining_amount);
    }

    public function test_overpay_rejected(): void
    {
        $ap = $this->payable(100000);

        $this->expectException(\RuntimeException::class);
        app(FinanceService::class)->payPayable($ap, 150000, $this->financeUser->id);
    }

    public function test_collect_receivable_creates_cash_in_and_journal(): void
    {
        $ar = $this->receivable(50000);
        $service = app(FinanceService::class);

        $result = $service->collectReceivable($ar, 50000, $this->financeUser->id);

        $this->assertSame('paid', $result['receivable']->status);

        $this->assertDatabaseHas('cash_transactions', [
            'type' => 'in',
            'category' => 'sales',
            'amount' => 50000,
        ]);

        $cashAccount = Account::where('code', '1110')->first();
        $receivableAccount = Account::where('code', '1210')->first();

        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => $cashAccount->id,
            'type' => 'debit',
            'amount' => 50000,
        ]);
        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => $receivableAccount->id,
            'type' => 'credit',
            'amount' => 50000,
        ]);
    }

    public function test_api_pay_endpoint_works(): void
    {
        $ap = $this->payable(100000);

        $this->actingAs($this->financeUser)
            ->postJson("/api/account-payables/{$ap->id}/pay", ['amount' => 50000])
            ->assertOk()
            ->assertJsonPath('data.status', 'partial');

        $this->assertEquals(50000, (float) $ap->fresh()->remaining_amount);
    }

    public function test_api_pay_rejects_overpayment(): void
    {
        $ap = $this->payable(100000);

        $this->actingAs($this->financeUser)
            ->postJson("/api/account-payables/{$ap->id}/pay", ['amount' => 999999])
            ->assertStatus(422);
    }

    public function test_sales_order_confirm_creates_receivable_and_journal(): void
    {
        $warehouse = Warehouse::factory()->create();
        $category = ProductCategory::factory()->create();
        $unit = ProductUnit::factory()->create();
        $customer = Customer::factory()->create();

        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'purchase_price' => 5000,
            'selling_price' => 8000,
        ]);

        Stock::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
        ]);

        $so = SalesOrder::create([
            'so_number' => 'SO-TEST-0001',
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => now()->toDateString(),
            'status' => 'draft',
            'subtotal' => 80000,
            'total_amount' => 80000,
            'payment_status' => 'unpaid',
            'created_by' => $this->financeUser->id,
        ]);

        $so->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 8000,
            'discount_amount' => 0,
            'subtotal' => 80000,
        ]);

        app(SalesOrderService::class)->confirm($so, $this->financeUser->id);

        // AR dibuat otomatis
        $this->assertDatabaseHas('account_receivables', [
            'reference_type' => 'sales_order',
            'reference_id' => $so->id,
            'total_amount' => 80000,
            'status' => 'open',
        ]);

        // Jurnal: Dr Piutang Pelanggan 80000 / Cr Penjualan Kredit 80000
        $receivableAccount = Account::where('code', '1210')->first();
        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => $receivableAccount->id,
            'type' => 'debit',
            'amount' => 80000,
        ]);

        // Jurnal COGS: Dr HPP 50000 / Cr Persediaan 50000 (10 x 5000)
        $cogsAccount = Account::where('code', '5100')->first();
        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => $cogsAccount->id,
            'type' => 'debit',
            'amount' => 50000,
        ]);
    }

    public function test_pos_checkout_creates_payment_cash_and_journal(): void
    {
        $warehouse = Warehouse::factory()->create();
        $category = ProductCategory::factory()->create();
        $unit = ProductUnit::factory()->create();

        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'purchase_price' => 3000,
            'selling_price' => 5000,
        ]);

        Stock::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 50,
        ]);

        $kasir = User::factory()->create();
        $kasir->roles()->attach(Role::where('name', 'kasir')->first());

        Livewire::actingAs($kasir)
            ->test(PosTerminal::class)
            ->set('warehouse_id', (string) $warehouse->id)
            ->call('addToCart', $product->id)
            ->set('payment_method', 'cash')
            ->set('paid_amount', '5000')
            ->call('checkout')
            ->assertHasNoErrors();

        $transaction = SalesTransaction::first();

        // Payment record dibuat
        $this->assertDatabaseHas('sales_transaction_payments', [
            'sales_transaction_id' => $transaction->id,
            'amount' => 5000,
        ]);

        // Kas masuk tercatat
        $this->assertDatabaseHas('cash_transactions', [
            'type' => 'in',
            'category' => 'sales',
            'amount' => 5000,
        ]);

        // Jurnal penjualan: Dr Kas 5000 / Cr Pendapatan 5000
        $cashAccount = Account::where('code', '1110')->first();
        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => $cashAccount->id,
            'type' => 'debit',
            'amount' => 5000,
        ]);

        // Jurnal COGS: Dr HPP 3000 / Cr Persediaan 3000 (1 x 3000)
        $cogsAccount = Account::where('code', '5100')->first();
        $this->assertDatabaseHas('journal_entry_lines', [
            'account_id' => $cogsAccount->id,
            'type' => 'debit',
            'amount' => 3000,
        ]);
    }

    public function test_dashboard_stats_include_product_count(): void
    {
        $stats = app(ReportService::class)->getDashboardStats();

        $this->assertArrayHasKey('total_products', $stats);
        $this->assertArrayHasKey('pending_ap', $stats);
        $this->assertArrayHasKey('pending_ar', $stats);
    }

    public function test_profit_loss_report_cogs_uses_sold_items(): void
    {
        $warehouse = Warehouse::factory()->create();
        $category = ProductCategory::factory()->create();
        $unit = ProductUnit::factory()->create();

        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'purchase_price' => 4000,
            'selling_price' => 6000,
        ]);

        // Transaksi penjualan: 2 unit x 6000 = 12000
        $tx = SalesTransaction::create([
            'transaction_number' => 'POS-TEST-0001',
            'warehouse_id' => $warehouse->id,
            'cashier_id' => $this->financeUser->id,
            'payment_method' => 'cash',
            'subtotal' => 12000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 12000,
            'paid_amount' => 12000,
            'change_amount' => 0,
            'status' => 'completed',
        ]);

        $tx->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 6000,
            'subtotal' => 12000,
        ]);

        $component = Livewire::test(ProfitLossReport::class)
            ->set('startDate', now()->startOfMonth()->toDateString())
            ->set('endDate', now()->endOfMonth()->toDateString());

        $report = $component->viewData('report');

        // COGS = 2 x 4000 (harga beli, belum ada GRN) = 8000, bukan 12000 (harga jual)
        $this->assertEqualsWithDelta(8000, $report['totalCogs'], 0.01);
        $this->assertEqualsWithDelta(12000, $report['totalRevenue'], 0.01);
        $this->assertEqualsWithDelta(4000, $report['grossProfit'], 0.01);
    }
}
