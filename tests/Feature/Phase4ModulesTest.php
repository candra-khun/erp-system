<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\ConsignmentList;
use App\Livewire\EmployeeList;
use App\Livewire\GatewayTransactionList;
use App\Livewire\MarketplaceChannelList;
use App\Livewire\PayrollList;
use App\Models\Employee;
use App\Models\GatewayTransaction;
use App\Models\MarketplaceChannel;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ConsignmentService;
use App\Services\MarketplaceSyncService;
use App\Services\PaymentGateway\PaymentGatewayManager;
use App\Services\PayrollService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regresi Fase 4 — HR & Payroll, Integrasi Marketplace, Payment Gateway,
 * dan Manajemen Konsinyasi (PRD roadmap Fase 4).
 */
class Phase4ModulesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        $this->warehouse = Warehouse::factory()->create();
        $this->admin = User::factory()->create();
        $this->admin->roles()->sync(Role::where('name', 'super_admin')->first());
    }

    // ------------------------------------------------------------------
    // HR & Payroll
    // ------------------------------------------------------------------

    public function test_employee_can_be_created_and_scoped(): void
    {
        $otherWarehouse = Warehouse::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(EmployeeList::class)
            ->set('employee_number', 'EMP-0001')
            ->set('full_name', 'Budi Santoso')
            ->set('warehouseId', (int) $this->warehouse->id)
            ->set('basic_salary', '5000000')
            ->call('save')
            ->assertHasNoErrors();

        $employee = Employee::firstOrFail();

        $this->assertSame('Budi Santoso', $employee->full_name);
        $this->assertSame('5000000.00', (string) $employee->basic_salary);
    }

    public function test_payroll_period_can_be_generated_and_paid(): void
    {
        $service = app(PayrollService::class);

        Employee::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'basic_salary' => 5000000,
            'is_active' => true,
        ]);

        $period = $service->createPeriod(2026, 9, (int) $this->warehouse->id, (int) $this->admin->id);

        $this->assertSame('draft', $period->status->value);

        $result = $service->generatePayrolls($period, ['working_days' => 22]);

        $this->assertSame(1, $result['created']);
        $this->assertSame('generated', $result['period']->status->value);

        $period = $result['period']->fresh();
        $service->approvePeriod($period, (int) $this->admin->id);

        $payroll = $period->fresh()->payrolls()->firstOrFail();

        $service->payPayroll($payroll, (int) $this->admin->id);

        $payroll = $payroll->fresh();

        $this->assertSame('paid', $payroll->status->value);
        $this->assertNotNull($payroll->paid_at);

        // Kas keluar tercatat.
        $this->assertDatabaseHas('cash_transactions', [
            'category' => 'salary',
            'reference_type' => 'payroll',
            'reference_id' => $payroll->id,
        ]);

        // Jurnal otomatis Dr Biaya Gaji / Cr Kas.
        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => 'payroll',
            'reference_id' => $payroll->id,
        ]);
    }

    public function test_payroll_period_cannot_be_duplicated(): void
    {
        $service = app(PayrollService::class);

        $service->createPeriod(2026, 9, (int) $this->warehouse->id, (int) $this->admin->id);

        $this->expectException(\RuntimeException::class);

        $service->createPeriod(2026, 9, (int) $this->warehouse->id, (int) $this->admin->id);
    }

    public function test_unapproved_payroll_cannot_be_paid(): void
    {
        $service = app(PayrollService::class);

        $employee = Employee::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'basic_salary' => 3000000,
            'is_active' => true,
        ]);

        $period = $service->createPeriod(2026, 8, (int) $this->warehouse->id, (int) $this->admin->id);
        $service->generatePayrolls($period);

        $payroll = $period->fresh()->payrolls()->firstOrFail();

        $this->expectException(\RuntimeException::class);

        $service->payPayroll($payroll, (int) $this->admin->id);
    }

    // ------------------------------------------------------------------
    // Marketplace
    // ------------------------------------------------------------------

    public function test_marketplace_order_can_be_converted_to_sales_order(): void
    {
        $channel = MarketplaceChannel::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'status' => 'active',
        ]);

        $product = Product::factory()->create();

        // Stok awal untuk dikurangi saat SO dikonfirmasi.
        Stock::firstOrCreate(
            ['product_id' => $product->id, 'warehouse_id' => $this->warehouse->id],
            ['quantity' => 100]
        );

        $service = app(MarketplaceSyncService::class);

        $order = $service->importOrder($channel, [
            'channel_order_number' => 'TP-001',
            'customer_name' => 'Pelanggan Online',
            'customer_phone' => '08123456789',
            'total_amount' => 150000,
            'shipping_cost' => 20000,
            'commission' => 5000,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 65000],
            ],
        ], (int) $this->admin->id);

        $this->assertSame('new', $order->status->value);

        $so = $service->convertToSalesOrder($order->fresh(), [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 65000],
            ],
        ], (int) $this->admin->id);

        $order = $order->fresh();

        $this->assertSame('converted', $order->status->value);
        $this->assertSame($so->id, $order->sales_order_id);
        $this->assertSame('confirmed', $so->fresh()->status);

        // Pelanggan otomatis dibuat.
        $this->assertDatabaseHas('customers', ['name' => 'Pelanggan Online']);

        // Stok berkurang setelah konfirmasi SO.
        $stock = Stock::where('product_id', $product->id)->firstOrFail();

        $this->assertSame('98.00', (string) $stock->quantity);
    }

    public function test_marketplace_order_cannot_be_imported_twice(): void
    {
        $channel = MarketplaceChannel::factory()->create(['status' => 'active']);

        $service = app(MarketplaceSyncService::class);

        $service->importOrder($channel, [
            'channel_order_id' => 'CH-999',
            'customer_name' => 'Test',
        ], (int) $this->admin->id);

        $this->expectException(\RuntimeException::class);

        $service->importOrder($channel, [
            'channel_order_id' => 'CH-999',
            'customer_name' => 'Test',
        ], (int) $this->admin->id);
    }

    // ------------------------------------------------------------------
    // Payment Gateway
    // ------------------------------------------------------------------

    public function test_gateway_transaction_manual_flow(): void
    {
        $manager = app(PaymentGatewayManager::class);

        $transaction = $manager->createTransaction([
            'provider' => 'manual',
            'amount' => 250000,
            'reference' => 'TEST-REF',
            'warehouse_id' => (int) $this->warehouse->id,
            'user_id' => (int) $this->admin->id,
        ]);

        $this->assertSame('pending', $transaction->status);
        $this->assertSame('250000.00', (string) $transaction->amount);

        $paid = $manager->markPaid($transaction, 'QRIS-REF-001');

        $this->assertSame('settlement', $paid->status);
        $this->assertNotNull($paid->paid_at);
        $this->assertSame('QRIS-REF-001', $paid->payment_reference);

        // Idempoten: markPaid kedua tidak mengubah data.
        $again = $manager->markPaid($paid->fresh(), 'QRIS-REF-002');

        $this->assertSame('settlement', $again->status);
        $this->assertSame('QRIS-REF-001', $again->payment_reference);
    }

    public function test_gateway_transaction_can_be_cancelled_when_pending(): void
    {
        $manager = app(PaymentGatewayManager::class);

        $transaction = $manager->createTransaction([
            'provider' => 'manual',
            'amount' => 100000,
            'warehouse_id' => (int) $this->warehouse->id,
        ]);

        $cancelled = $manager->cancel($transaction);

        $this->assertSame('cancel', $cancelled->status);
    }

    public function test_paid_gateway_transaction_cannot_be_cancelled(): void
    {
        $manager = app(PaymentGatewayManager::class);

        $transaction = $manager->createTransaction([
            'provider' => 'manual',
            'amount' => 50000,
            'warehouse_id' => (int) $this->warehouse->id,
        ]);

        $manager->markPaid($transaction);

        $this->expectException(\RuntimeException::class);

        $manager->cancel($transaction->fresh());
    }

    public function test_gateway_scoped_transactions_list(): void
    {
        $otherWarehouse = Warehouse::factory()->create();

        $own = GatewayTransaction::factory()->create(['warehouse_id' => $this->warehouse->id]);
        $foreign = GatewayTransaction::factory()->create(['warehouse_id' => $otherWarehouse->id]);

        // super_admin melihat semua.
        $component = Livewire::actingAs($this->admin)->test(GatewayTransactionList::class);

        $ids = $component->instance()->transactions->getCollection()->pluck('id')->all();

        $this->assertContains($own->id, $ids);
        $this->assertContains($foreign->id, $ids);
    }

    // ------------------------------------------------------------------
    // Consignment
    // ------------------------------------------------------------------

    public function test_consignment_receive_increases_stock(): void
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $service = app(ConsignmentService::class);

        $consignment = $service->receiveConsignment(
            (int) $supplier->id,
            (int) $this->warehouse->id,
            [
                ['product_id' => $product->id, 'quantity' => 20, 'consignment_price' => 5000, 'selling_price' => 8000],
            ],
            (int) $this->admin->id,
        );

        $stock = Stock::where('product_id', $product->id)->firstOrFail();

        $this->assertSame('20.00', (string) $stock->quantity);
        $this->assertSame('20.00', (string) $consignment->items()->first()->quantity_available);
    }

    public function test_consignment_sale_decreases_available(): void
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $service = app(ConsignmentService::class);

        $consignment = $service->receiveConsignment(
            (int) $supplier->id,
            (int) $this->warehouse->id,
            [
                ['product_id' => $product->id, 'quantity' => 10, 'consignment_price' => 5000, 'selling_price' => 8000],
            ],
            (int) $this->admin->id,
        );

        $service->recordSale((int) $product->id, (int) $this->warehouse->id, 4);

        $item = $consignment->fresh()->items()->first();

        $this->assertSame('4.00', (string) $item->quantity_sold);
        $this->assertSame('6.00', (string) $item->quantity_available);
        $this->assertSame('6.00', (string) Stock::where('product_id', $product->id)->value('quantity'));
    }

    public function test_consignment_sale_insufficient_stock_throws(): void
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $service = app(ConsignmentService::class);

        $service->receiveConsignment(
            (int) $supplier->id,
            (int) $this->warehouse->id,
            [
                ['product_id' => $product->id, 'quantity' => 2, 'consignment_price' => 5000, 'selling_price' => 8000],
            ],
            (int) $this->admin->id,
        );

        $this->expectException(\RuntimeException::class);

        $service->recordSale((int) $product->id, (int) $this->warehouse->id, 10);
    }

    public function test_consignment_settlement_creates_payable_and_journal(): void
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $service = app(ConsignmentService::class);

        $consignment = $service->receiveConsignment(
            (int) $supplier->id,
            (int) $this->warehouse->id,
            [
                ['product_id' => $product->id, 'quantity' => 10, 'consignment_price' => 5000, 'selling_price' => 8000],
            ],
            (int) $this->admin->id,
        );

        $service->recordSale((int) $product->id, (int) $this->warehouse->id, 6);

        $settlement = $service->createSettlement((int) $supplier->id, (int) $this->warehouse->id, (int) $this->admin->id);

        // 6 terjual × 5000 = 30000.
        $this->assertSame('30000.00', (string) $settlement->total_amount);
        $this->assertSame('6.00', (string) $settlement->total_quantity_sold);

        $confirmed = $service->confirmSettlement($settlement, (int) $this->admin->id);

        $this->assertSame('confirmed', $confirmed->status->value);
        $this->assertNotNull($confirmed->account_payable_id);

        // AP ke supplier terbentuk.
        $this->assertDatabaseHas('account_payables', [
            'supplier_id' => $supplier->id,
            'reference_type' => 'consignment_settlement',
            'reference_id' => $settlement->id,
        ]);

        // Jurnal Dr Persediaan / Cr Hutang Supplier.
        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => 'consignment_settlement',
            'reference_id' => $settlement->id,
        ]);

        // Item ditandai settled.
        $this->assertSame('settled', $consignment->fresh()->items()->first()->status);
    }

    public function test_consignment_settlement_without_sales_throws(): void
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $service = app(ConsignmentService::class);

        $service->receiveConsignment(
            (int) $supplier->id,
            (int) $this->warehouse->id,
            [
                ['product_id' => $product->id, 'quantity' => 10, 'consignment_price' => 5000, 'selling_price' => 8000],
            ],
            (int) $this->admin->id,
        );

        $this->expectException(\RuntimeException::class);

        $service->createSettlement((int) $supplier->id, (int) $this->warehouse->id, (int) $this->admin->id);
    }

    // ------------------------------------------------------------------
    // Sidebar / permission
    // ------------------------------------------------------------------

    public function test_phase4_permissions_are_registered(): void
    {
        foreach (['manage-employees', 'manage-payroll', 'manage-marketplace', 'manage-gateway', 'manage-consignment'] as $permission) {
            $this->assertDatabaseHas('permissions', ['name' => $permission]);
        }
    }

    public function test_employee_page_renders_for_admin(): void
    {
        Livewire::actingAs($this->admin)
            ->test(EmployeeList::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.employee-list');
    }

    public function test_payroll_page_renders_for_admin(): void
    {
        Livewire::actingAs($this->admin)
            ->test(PayrollList::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.payroll-list');
    }

    public function test_marketplace_channel_page_renders_for_admin(): void
    {
        Livewire::actingAs($this->admin)
            ->test(MarketplaceChannelList::class)
            ->assertStatus(200);
    }

    public function test_consignment_page_renders_for_admin(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ConsignmentList::class)
            ->assertStatus(200);
    }

    public function test_gateway_transaction_page_renders_for_admin(): void
    {
        Livewire::actingAs($this->admin)
            ->test(GatewayTransactionList::class)
            ->assertStatus(200);
    }
}
