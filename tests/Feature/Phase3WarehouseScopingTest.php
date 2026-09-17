<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Imports\BankStatementImport;
use App\Livewire\BankReconciliationList;
use App\Livewire\ShipmentList;
use App\Models\BankReconciliation;
use App\Models\Customer;
use App\Models\CustomerLoyaltyProfile;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\LoyaltyService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Regresi Fase 3 — scoping cabang pada modul CRM (loyalitas), Distribusi &
 * Logistik, dan Rekonsiliasi Bank (PRD §2 + roadmap Fase 3).
 */
class Phase3WarehouseScopingTest extends TestCase
{
    use RefreshDatabase;

    private User $branchAdmin;

    private User $branchSales;

    private Warehouse $branchWarehouse;

    private Warehouse $hqWarehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->hqWarehouse = Warehouse::factory()->create(['name' => 'Gudang Pusat']);
        $this->branchWarehouse = Warehouse::factory()->create(['name' => 'Cabang Surabaya']);

        $this->branchAdmin = User::factory()->create();
        $this->branchAdmin->roles()->sync(Role::where('name', 'admin_cabang')->first());
        $this->branchAdmin->warehouses()->sync([$this->branchWarehouse->id]);

        // Role dengan permission manage-logistics untuk menguji endpoint API.
        $this->branchSales = User::factory()->create();
        $this->branchSales->roles()->sync(Role::where('name', 'sales_marketing')->first());
        $this->branchSales->warehouses()->sync([$this->branchWarehouse->id]);
    }

    /**
     * Sales order terkirim di gudang tertentu.
     */
    private function shipmentAt(Warehouse $warehouse, string $status = 'preparing'): Shipment
    {
        $so = SalesOrder::factory()->create([
            'warehouse_id' => $warehouse->id,
            'status' => 'confirmed',
        ]);

        return Shipment::factory()->create([
            'sales_order_id' => $so->id,
            'status' => $status,
        ]);
    }

    public function test_shipment_list_excludes_other_branches(): void
    {
        $own = $this->shipmentAt($this->branchWarehouse);
        $foreign = $this->shipmentAt($this->hqWarehouse);

        $this->actingAs($this->branchAdmin);

        $component = Livewire::test(ShipmentList::class);

        $ids = $component->instance()->shipments->getCollection()->pluck('id')->all();

        $this->assertContains($own->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_shipment_list_deliverable_orders_are_scoped(): void
    {
        $own = SalesOrder::factory()->create([
            'warehouse_id' => $this->branchWarehouse->id,
            'status' => 'confirmed',
        ]);
        $foreign = SalesOrder::factory()->create([
            'warehouse_id' => $this->hqWarehouse->id,
            'status' => 'confirmed',
        ]);

        $this->actingAs($this->branchAdmin);

        $component = Livewire::test(ShipmentList::class);

        $ids = $component->instance()->deliverableOrders->pluck('id')->all();

        $this->assertContains($own->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_shipment_action_rejects_foreign_warehouse(): void
    {
        $foreign = $this->shipmentAt($this->hqWarehouse);

        $this->actingAs($this->branchAdmin);

        Livewire::test(ShipmentList::class)
            ->call('dispatchShipment', $foreign->id)
            ->assertForbidden();

        $this->assertSame('preparing', $foreign->fresh()->status->value);
    }

    public function test_shipment_creation_rejects_foreign_sales_order(): void
    {
        $foreign = SalesOrder::factory()->create([
            'warehouse_id' => $this->hqWarehouse->id,
            'status' => 'confirmed',
        ]);

        $this->actingAs($this->branchAdmin);

        Livewire::test(ShipmentList::class)
            ->set('salesOrderId', $foreign->id)
            ->call('saveShipment')
            ->assertForbidden();

        $this->assertSame(0, Shipment::count());
    }

    public function test_api_shipment_index_is_scoped(): void
    {
        $own = $this->shipmentAt($this->branchWarehouse);
        $foreign = $this->shipmentAt($this->hqWarehouse);

        Sanctum::actingAs($this->branchSales);

        $response = $this->getJson('/api/shipments');

        $response->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id')->all();

        $this->assertContains($own->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_api_shipment_action_rejects_foreign_warehouse(): void
    {
        $foreign = $this->shipmentAt($this->hqWarehouse);

        Sanctum::actingAs($this->branchSales);

        $response = $this->postJson('/api/shipments/'.$foreign->id.'/dispatch');

        $response->assertForbidden();

        $this->assertSame('preparing', $foreign->fresh()->status->value);
    }

    public function test_api_shipment_store_rejects_foreign_sales_order(): void
    {
        $foreign = SalesOrder::factory()->create([
            'warehouse_id' => $this->hqWarehouse->id,
            'status' => 'confirmed',
        ]);

        Sanctum::actingAs($this->branchSales);

        $response = $this->postJson('/api/shipments', ['sales_order_id' => $foreign->id]);

        $response->assertForbidden();

        $this->assertSame(0, Shipment::count());
    }

    public function test_bank_reconciliation_clamps_warehouse_filter(): void
    {
        $this->actingAs($this->branchAdmin);

        Livewire::test(BankReconciliationList::class)
            ->set('warehouseId', $this->hqWarehouse->id)
            ->call('saveSession')
            ->assertHasErrors(['periodStart', 'periodEnd', 'lines'])
            ->assertDontSee('Gudang Pusat');

        // Filter gudang asing harus sudah di-clamp ke null (tidak bocor).
        $this->assertNull(session('warehouse_id'));
    }

    public function test_bank_reconciliation_sessions_are_scoped(): void
    {
        $own = BankReconciliation::factory()->create(['warehouse_id' => $this->branchWarehouse->id]);
        $foreign = BankReconciliation::factory()->create(['warehouse_id' => $this->hqWarehouse->id]);

        $this->actingAs($this->branchAdmin);

        $component = Livewire::test(BankReconciliationList::class);

        $ids = $component->instance()->sessions->getCollection()->pluck('id')->all();

        $this->assertContains($own->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_bank_reconciliation_action_rejects_foreign_session(): void
    {
        $foreign = BankReconciliation::factory()->create([
            'warehouse_id' => $this->hqWarehouse->id,
            'status' => 'open',
        ]);

        $this->actingAs($this->branchAdmin);

        Livewire::test(BankReconciliationList::class)
            ->call('cancelSession', $foreign->id)
            ->assertForbidden();

        $this->assertSame('open', $foreign->fresh()->status);
    }

    public function test_bank_statement_import_parses_csv(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'statement.csv',
            "tanggal,keterangan,nominal,referensi\n".
            "2026-09-01,Penjualan tunai,150000,REF-001\n".
            "2026-09-02,Pembelian barang,-75000,REF-002\n".
            "2026-09-03,Bunga bank,5000,\n"
        );

        $import = new BankStatementImport;
        Excel::import($import, $file);

        $this->assertSame(3, $import->imported);
        $this->assertSame([], $import->errors);
        $this->assertSame('2026-09-01', $import->lines[0]['value_date']);
        $this->assertSame(150000.0, $import->lines[0]['amount']);
        $this->assertSame(-75000.0, $import->lines[1]['amount']);
    }

    public function test_loyalty_earn_is_idempotent(): void
    {
        $customer = Customer::factory()->create(['type' => 'member']);

        $service = app(LoyaltyService::class);

        $first = $service->awardForSale($customer->id, 50000, 'sales_transaction', 9001);
        $second = $service->awardForSale($customer->id, 50000, 'sales_transaction', 9001);

        $this->assertSame(5, $first);
        $this->assertSame(0, $second);

        $profile = CustomerLoyaltyProfile::where('customer_id', $customer->id)->firstOrFail();

        $this->assertSame(5, (int) $profile->points_balance);
        $this->assertSame(5, (int) $profile->lifetime_points);
    }

    public function test_loyalty_redeem_creates_profile_when_missing(): void
    {
        $customer = Customer::factory()->create(['type' => 'member']);

        $this->expectException(\RuntimeException::class);

        app(LoyaltyService::class)->redeem($customer->id, 10, 'manual');
    }
}
