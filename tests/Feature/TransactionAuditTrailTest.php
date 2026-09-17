<?php

namespace Tests\Feature;

use App\Livewire\PosTerminal;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesTransaction;
use App\Models\Stock;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchaseOrderService;
use App\Services\SalesOrderService;
use App\Services\StockTransferService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TransactionAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Warehouse $warehouse;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->user = User::factory()->create();
        $purchasingRole = Role::where('name', 'staff_pembelian')->first();
        $this->user->roles()->attach($purchasingRole);

        $this->warehouse = Warehouse::factory()->create();

        $category = ProductCategory::factory()->create();
        $unit = ProductUnit::factory()->create();

        $this->product = Product::factory()->create([
            'product_category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'selling_price' => 10000,
        ]);

        Stock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 50,
        ]);
    }

    public function test_po_approval_is_audited(): void
    {
        $supplier = Supplier::factory()->create();

        $po = app(PurchaseOrderService::class)->createPurchaseOrder(
            ['supplier_id' => $supplier->id, 'warehouse_id' => $this->warehouse->id, 'order_date' => now()->toDateString()],
            [['product_id' => $this->product->id, 'quantity' => 5, 'unit_price' => 5000]],
            $this->user->id,
        );

        app(PurchaseOrderService::class)->approve($po, $this->user->id);

        $log = AuditLog::where('auditable_type', PurchaseOrder::class)
            ->where('auditable_id', $po->id)
            ->where('action', 'status_changed')
            ->first();

        $this->assertNotNull($log, 'Audit log approval PO tidak dibuat.');
        $this->assertSame($this->user->id, $log->user_id);
        $this->assertSame('draft/submitted', $log->old_values['status']);
        $this->assertSame('approved', $log->new_values['status']);
    }

    public function test_grn_creation_is_audited(): void
    {
        $supplier = Supplier::factory()->create();

        $po = app(PurchaseOrderService::class)->createPurchaseOrder(
            ['supplier_id' => $supplier->id, 'warehouse_id' => $this->warehouse->id, 'order_date' => now()->toDateString()],
            [['product_id' => $this->product->id, 'quantity' => 5, 'unit_price' => 5000]],
            $this->user->id,
        );
        $po = app(PurchaseOrderService::class)->approve($po, $this->user->id);

        app(PurchaseOrderService::class)->receiveGoods(
            $po->fresh(),
            [['purchase_order_item_id' => $po->items->first()->id, 'quantity' => 5]],
            $this->user->id,
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'goods_received',
            'auditable_type' => GoodsReceipt::class,
            'user_id' => $this->user->id,
        ]);

        // Transisi status PO juga tercatat
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'status_changed',
            'auditable_type' => PurchaseOrder::class,
            'auditable_id' => $po->id,
        ]);
    }

    public function test_po_submit_to_supplier_is_audited(): void
    {
        $supplier = Supplier::factory()->create();

        $po = app(PurchaseOrderService::class)->createPurchaseOrder(
            ['supplier_id' => $supplier->id, 'warehouse_id' => $this->warehouse->id, 'order_date' => now()->toDateString()],
            [['product_id' => $this->product->id, 'quantity' => 5, 'unit_price' => 5000]],
            $this->user->id,
        );
        $approved = app(PurchaseOrderService::class)->approve($po, $this->user->id);

        $this->actingAs($this->user);
        app(PurchaseOrderService::class)->submitToSupplier($approved);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'status_changed',
            'auditable_type' => PurchaseOrder::class,
            'auditable_id' => $approved->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_so_confirm_and_cancel_are_audited(): void
    {
        $customer = Customer::factory()->create();

        $so = SalesOrder::create([
            'so_number' => 'SO-TEST-0001',
            'customer_id' => $customer->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => now()->toDateString(),
            'status' => 'draft',
            'subtotal' => 50000,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 50000,
            'paid_amount' => 0,
            'payment_status' => 'unpaid',
            'created_by' => $this->user->id,
        ]);
        $so->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_price' => 10000,
            'discount_amount' => 0,
            'subtotal' => 50000,
        ]);

        $confirmed = app(SalesOrderService::class)->confirm($so, $this->user->id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'status_changed',
            'auditable_type' => SalesOrder::class,
            'auditable_id' => $so->id,
            'user_id' => $this->user->id,
        ]);

        app(SalesOrderService::class)->cancel($confirmed->fresh(), $this->user->id);

        $cancelLog = AuditLog::where('auditable_type', SalesOrder::class)
            ->where('auditable_id', $so->id)
            ->where('action', 'status_changed')
            ->where('new_values', 'like', '%"cancelled"%')
            ->first();

        $this->assertNotNull($cancelLog, 'Audit log pembatalan SO tidak dibuat.');
    }

    public function test_transfer_approve_and_receive_are_audited(): void
    {
        $destination = Warehouse::factory()->create();

        $transfer = app(StockTransferService::class)->createTransfer(
            [
                'source_warehouse_id' => $this->warehouse->id,
                'destination_warehouse_id' => $destination->id,
            ],
            [['product_id' => $this->product->id, 'quantity' => 5]],
            $this->user->id,
        );

        app(StockTransferService::class)->approve($transfer, $this->user->id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'status_changed',
            'auditable_type' => StockTransfer::class,
            'auditable_id' => $transfer->id,
            'user_id' => $this->user->id,
        ]);

        app(StockTransferService::class)->receive($transfer->fresh(), [
            ['stock_transfer_item_id' => $transfer->items->first()->id, 'quantity' => 5],
        ], $this->user->id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'transfer_received',
            'auditable_type' => StockTransfer::class,
            'auditable_id' => $transfer->id,
        ]);
    }

    public function test_pos_checkout_is_audited(): void
    {
        Livewire::actingAs($this->user)
            ->test(PosTerminal::class)
            ->set('warehouse_id', (string) $this->warehouse->id)
            ->call('addToCart', $this->product->id)
            ->set('payment_method', 'cash')
            ->set('paid_amount', '10000')
            ->call('checkout')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'pos_sale',
            'auditable_type' => SalesTransaction::class,
            'user_id' => $this->user->id,
        ]);
    }
}
