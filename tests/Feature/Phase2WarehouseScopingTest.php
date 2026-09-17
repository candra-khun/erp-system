<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\ChartOfAccounts;
use App\Livewire\JournalEntryList;
use App\Livewire\SalesReport;
use App\Livewire\StockOpnameList;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesTransaction;
use App\Models\SalesTransactionItem;
use App\Models\StockOpname;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regresi Fase 2 — scoping cabang pada modul Keuangan/Laporan/Opname
 * (PRD §2: Admin Cabang hanya mengelola 1 cabang).
 */
class Phase2WarehouseScopingTest extends TestCase
{
    use RefreshDatabase;

    private User $branchAdmin;

    private Warehouse $branchWarehouse;

    private Warehouse $hqWarehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->hqWarehouse = Warehouse::factory()->create(['name' => 'Gudang Pusat']);
        $this->branchWarehouse = Warehouse::factory()->create(['name' => 'Cabang Bandung']);

        $this->branchAdmin = User::factory()->create();
        $this->branchAdmin->roles()->sync(Role::where('name', 'admin_cabang')->first());
        $this->branchAdmin->warehouses()->sync([$this->branchWarehouse->id]);
    }

    /**
     * Buat satu baris penjualan (transaksi + item) untuk gudang tertentu.
     */
    private function createSale(Warehouse $warehouse, Product $product, float $amount): SalesTransaction
    {
        $transaction = SalesTransaction::factory()->create([
            'warehouse_id' => $warehouse->id,
            'total_amount' => $amount,
        ]);

        SalesTransactionItem::factory()->create([
            'sales_transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => $amount,
            'subtotal' => $amount,
        ]);

        return $transaction;
    }

    public function test_sales_report_clamps_foreign_warehouse_filter(): void
    {
        $this->actingAs($this->branchAdmin);

        Livewire::withQueryParams(['warehouseId' => (string) $this->hqWarehouse->id])
            ->test(SalesReport::class)
            ->assertSet('warehouseId', '');
    }

    public function test_sales_report_excludes_other_branches_from_revenue(): void
    {
        $product = Product::factory()->create();
        $this->createSale($this->branchWarehouse, $product, 100000);
        $this->createSale($this->hqWarehouse, $product, 900000);

        $this->actingAs($this->branchAdmin);

        Livewire::test(SalesReport::class)
            ->assertViewHas('data', function ($data): bool {
                $revenue = collect($data)->sum(fn ($row) => (float) $row->total_revenue);

                return abs($revenue - 100000.0) < 0.01;
            });
    }

    public function test_sales_report_by_warehouse_grouping_is_scoped(): void
    {
        $product = Product::factory()->create();
        $this->createSale($this->branchWarehouse, $product, 50000);
        $this->createSale($this->hqWarehouse, $product, 750000);

        $this->actingAs($this->branchAdmin);

        Livewire::test(SalesReport::class)
            ->set('groupBy', 'warehouse')
            ->assertViewHas('data', function ($data): bool {
                $names = collect($data)->pluck('warehouse')->all();

                return in_array('Cabang Bandung', $names, true)
                    && ! in_array('Gudang Pusat', $names, true);
            });
    }

    public function test_stock_opname_list_is_scoped_to_assigned_warehouse(): void
    {
        $own = StockOpname::factory()->create(['warehouse_id' => $this->branchWarehouse->id]);
        $foreign = StockOpname::factory()->create(['warehouse_id' => $this->hqWarehouse->id]);

        $this->actingAs($this->branchAdmin);

        Livewire::test(StockOpnameList::class)
            ->assertViewHas('opnames', fn ($opnames) => $opnames->getCollection()->contains('id', $own->id)
                && ! $opnames->getCollection()->contains('id', $foreign->id));
    }

    public function test_stock_opname_approve_rejects_foreign_warehouse(): void
    {
        $foreign = StockOpname::factory()->create([
            'warehouse_id' => $this->hqWarehouse->id,
            'status' => 'draft',
        ]);

        $this->actingAs($this->branchAdmin);

        Livewire::test(StockOpnameList::class)
            ->call('approve', $foreign->id)
            ->assertForbidden();
    }

    public function test_stock_opname_start_rejects_foreign_warehouse(): void
    {
        $foreign = StockOpname::factory()->create([
            'warehouse_id' => $this->hqWarehouse->id,
            'status' => 'draft',
        ]);

        $this->actingAs($this->branchAdmin);

        Livewire::test(StockOpnameList::class)
            ->call('start', $foreign->id)
            ->assertForbidden();
    }

    public function test_api_sales_by_product_excludes_other_branches(): void
    {
        $product = Product::factory()->create();
        $this->createSale($this->branchWarehouse, $product, 120000);
        $this->createSale($this->hqWarehouse, $product, 880000);

        Sanctum::actingAs($this->branchAdmin);

        $response = $this->getJson('/api/reports/sales-by-product?start_date='.now()->startOfMonth()->toDateString().'&end_date='.now()->toDateString());

        $response->assertOk();

        $revenue = collect($response->json('data'))->sum(fn ($row) => (float) $row['total_revenue']);

        $this->assertEqualsWithDelta(120000.0, $revenue, 0.01);
    }

    public function test_api_sales_by_product_rejects_foreign_warehouse_filter(): void
    {
        Sanctum::actingAs($this->branchAdmin);

        $response = $this->getJson('/api/reports/sales-by-product?start_date='.now()->startOfMonth()->toDateString().'&end_date='.now()->toDateString().'&warehouse_id='.$this->hqWarehouse->id);

        $response->assertStatus(422)->assertJsonValidationErrors('warehouse_id');
    }

    public function test_api_dashboard_stats_are_scoped_for_branch_admin(): void
    {
        $product = Product::factory()->create();
        $this->createSale($this->branchWarehouse, $product, 200000);
        $this->createSale($this->hqWarehouse, $product, 800000);

        Sanctum::actingAs($this->branchAdmin);

        $response = $this->getJson('/api/dashboard');

        $response->assertOk();

        $this->assertEqualsWithDelta(200000.0, (float) $response->json('stats.today_sales'), 0.01);
    }

    public function test_journal_search_keeps_status_filter_applied(): void
    {
        $user = User::factory()->create();
        $user->roles()->sync(Role::where('name', 'staff_keuangan')->first());

        JournalEntry::factory()->create([
            'journal_number' => 'JV-TEST-0001',
            'description' => 'Penjualan tunai',
            'is_posted' => true,
        ]);
        JournalEntry::factory()->create([
            'journal_number' => 'JV-TEST-0002',
            'description' => 'Penjualan kredit',
            'is_posted' => false,
        ]);

        $this->actingAs($user);

        Livewire::test(JournalEntryList::class)
            ->set('search', 'JV-TEST')
            ->set('statusFilter', 'draft')
            ->assertViewHas('entries', fn ($entries) => $entries->getCollection()->count() === 1
                && $entries->getCollection()->first()->journal_number === 'JV-TEST-0002');
    }

    public function test_chart_of_accounts_search_does_not_leak_soft_deleted(): void
    {
        $account = Account::factory()->create(['name' => 'Akun Uji Bocor']);
        $account->delete();

        $user = User::factory()->create();
        $user->roles()->sync(Role::where('name', 'staff_keuangan')->first());

        $this->actingAs($user);

        Livewire::test(ChartOfAccounts::class)
            ->set('search', 'Uji Bocor')
            ->assertViewHas('accounts', fn ($accounts) => $accounts->getCollection()->isEmpty());
    }
}
