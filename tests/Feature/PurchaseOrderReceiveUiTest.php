<?php

namespace Tests\Feature;

use App\Livewire\PurchaseOrderList;
use App\Models\AccountPayable;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchaseOrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseOrderReceiveUiTest extends TestCase
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
            'purchase_price' => 5000,
        ]);

        Stock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 0,
        ]);
    }

    private function createApprovedPo(): PurchaseOrder
    {
        $supplier = Supplier::factory()->create();

        $po = app(PurchaseOrderService::class)->createPurchaseOrder(
            [
                'supplier_id' => $supplier->id,
                'warehouse_id' => $this->warehouse->id,
                'order_date' => now()->toDateString(),
            ],
            [['product_id' => $this->product->id, 'quantity' => 10, 'unit_price' => 5000]],
            $this->user->id,
        );

        return app(PurchaseOrderService::class)->approve($po, $this->user->id);
    }

    public function test_receive_form_is_preloaded_with_remaining_quantity(): void
    {
        $po = $this->createApprovedPo();

        Livewire::actingAs($this->user)
            ->test(PurchaseOrderList::class)
            ->call('openReceive', $po->id)
            ->assertSet('receiveItems', fn ($items) => count($items) === 1
                && (float) $items[0]['ordered'] === 10.0
                && (float) $items[0]['received'] === 0.0
                && $items[0]['quantity'] === '10'
                && array_key_exists('batch_number', $items[0])
                && array_key_exists('expiry_date', $items[0]));
    }

    public function test_receive_via_ui_creates_grn_updates_stock_and_ap(): void
    {
        $po = $this->createApprovedPo();

        Livewire::actingAs($this->user)
            ->test(PurchaseOrderList::class)
            ->call('openReceive', $po->id)
            ->set('receiveItems.0.quantity', '6')
            ->set('receiveItems.0.batch_number', 'BATCH-001')
            ->set('receiveItems.0.expiry_date', now()->addYear()->toDateString())
            ->call('submitReceive')
            ->assertHasNoErrors()
            ->assertSet('showReceiveForm', false);

        // GRN + item tersimpan dengan batch/expiry
        $gr = GoodsReceipt::where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($gr);
        $this->assertDatabaseHas('goods_receipt_items', [
            'goods_receipt_id' => $gr->id,
            'product_id' => $this->product->id,
            'quantity' => 6,
            'batch_number' => 'BATCH-001',
        ]);

        // Stok bertambah di gudang
        $this->assertDatabaseHas('stocks', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 6,
        ]);

        // Hutang terbentuk
        $this->assertDatabaseHas('account_payables', [
            'reference_type' => 'goods_receipt',
            'reference_id' => $gr->id,
            'total_amount' => 6 * 5000,
        ]);

        // PO jadi partial_received, sisa tetap terbuka
        $this->assertSame('partial_received', $po->fresh()->status);
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $po->id,
            'received_quantity' => 6,
        ]);
    }

    public function test_receive_rejects_past_expiry_date(): void
    {
        $po = $this->createApprovedPo();

        Livewire::actingAs($this->user)
            ->test(PurchaseOrderList::class)
            ->call('openReceive', $po->id)
            ->set('receiveItems.0.quantity', '1')
            ->set('receiveItems.0.expiry_date', now()->subDay()->toDateString())
            ->call('submitReceive')
            ->assertHasErrors();

        $this->assertDatabaseCount('goods_receipts', 0);
    }

    public function test_receive_rejects_over_receipt(): void
    {
        $po = $this->createApprovedPo();

        Livewire::actingAs($this->user)
            ->test(PurchaseOrderList::class)
            ->call('openReceive', $po->id)
            ->set('receiveItems.0.quantity', '11')
            ->call('submitReceive');

        // Service melempar exception -> transaksi rollback, tidak ada GRN
        $this->assertDatabaseCount('goods_receipts', 0);
    }

    public function test_goods_receipt_list_page_renders(): void
    {
        $response = $this->actingAs($this->user)->get('/goods-receipts');

        $response->assertOk();
    }

    public function test_ap_and_journal_flow_after_full_receipt(): void
    {
        $po = $this->createApprovedPo();

        Livewire::actingAs($this->user)
            ->test(PurchaseOrderList::class)
            ->call('openReceive', $po->id)
            ->set('receiveItems.0.quantity', '10')
            ->call('submitReceive')
            ->assertHasNoErrors();

        $this->assertSame('received', $po->fresh()->status);

        $ap = AccountPayable::where('reference_type', 'goods_receipt')->first();
        $this->assertNotNull($ap);
        $this->assertSame(10 * 5000, (int) $ap->total_amount);
    }
}
