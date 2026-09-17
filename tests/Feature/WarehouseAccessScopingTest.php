<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\BankReconciliationList;
use App\Livewire\CashTransactionList;
use App\Livewire\StockList;
use App\Models\BankReconciliation;
use App\Models\CashTransaction;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\WarehouseAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WarehouseAccessScopingTest extends TestCase
{
    use RefreshDatabase;

    private User $branchAdmin;

    private Warehouse $branchWarehouse;

    private Warehouse $hqWarehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\RolesAndPermissionsSeeder::class);

        $this->hqWarehouse = Warehouse::factory()->create(['name' => 'Gudang Pusat']);
        $this->branchWarehouse = Warehouse::factory()->create(['name' => 'Cabang Bandung']);

        // Admin cabang: hanya di-assign ke Cabang Bandung
        $this->branchAdmin = User::factory()->create();
        $this->branchAdmin->roles()->sync(Role::where('name', 'admin_cabang')->first());
        $this->branchAdmin->warehouses()->sync([$this->branchWarehouse->id]);
    }

    public function test_support_class_scopes_correctly(): void
    {
        $this->assertTrue(WarehouseAccess::canAccess($this->branchAdmin, $this->branchWarehouse->id));
        $this->assertFalse(WarehouseAccess::canAccess($this->branchAdmin, $this->hqWarehouse->id));

        $this->assertSame(
            [$this->branchWarehouse->id],
            WarehouseAccess::assignedWarehouseIds($this->branchAdmin)
        );
        $this->assertTrue(WarehouseAccess::isRestricted($this->branchAdmin));

        // User tanpa assignment = akses penuh (staf pusat)
        $hqStaff = User::factory()->create();
        $this->assertNull(WarehouseAccess::assignedWarehouseIds($hqStaff));
        $this->assertFalse(WarehouseAccess::isRestricted($hqStaff));

        // super_admin selalu penuh
        $superAdmin = User::factory()->create();
        $superAdmin->roles()->sync(Role::where('name', 'super_admin')->first());
        $this->assertTrue(WarehouseAccess::canAccess($superAdmin, $this->hqWarehouse->id));
    }

    public function test_branch_admin_sees_only_assigned_warehouse_in_stock_list(): void
    {
        $product = Product::factory()->create();
        Stock::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->hqWarehouse->id,
        ]);
        Stock::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->branchWarehouse->id,
        ]);

        $this->actingAs($this->branchAdmin);

        Livewire::test(StockList::class)
            ->assertSet('warehouseFilter', '')
            ->assertViewHas('warehouses', fn ($options) => $options->pluck('id')->contains($this->branchWarehouse->id)
                && ! $options->pluck('id')->contains($this->hqWarehouse->id))
            ->assertViewHas('stocks', fn ($stocks) => $stocks->getCollection()
                ->every(fn ($stock) => $stock->warehouse_id === $this->branchWarehouse->id));
    }

    public function test_branch_admin_cannot_filter_stock_to_foreign_warehouse(): void
    {
        $product = Product::factory()->create();
        Stock::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->hqWarehouse->id,
        ]);

        $this->actingAs($this->branchAdmin);

        // Coba filter via query string ke gudang pusat (bypass attempt)
        Livewire::withQueryParams(['warehouseFilter' => (string) $this->hqWarehouse->id])
            ->test(StockList::class)
            ->assertSet('warehouseFilter', '');
    }

    public function test_branch_admin_cash_transaction_list_is_scoped(): void
    {
        CashTransaction::factory()->create([
            'warehouse_id' => $this->hqWarehouse->id,
        ]);
        CashTransaction::factory()->create([
            'warehouse_id' => $this->branchWarehouse->id,
        ]);

        $this->actingAs($this->branchAdmin);

        Livewire::test(CashTransactionList::class)
            ->assertViewHas('transactions', fn ($transactions) => $transactions->getCollection()
                ->every(fn ($tx) => $tx->warehouse_id === $this->branchWarehouse->id));
    }

    public function test_branch_admin_cannot_create_cash_transaction_for_foreign_warehouse(): void
    {
        $this->actingAs($this->branchAdmin);

        Livewire::test(CashTransactionList::class)
            ->set('type', 'in')
            ->set('category', 'sales')
            ->set('amount', 100000)
            ->set('transactionDate', '2026-09-15')
            ->set('warehouseId', $this->hqWarehouse->id)
            ->call('store')
            ->assertHasErrors('warehouseId');

        $this->assertDatabaseMissing('cash_transactions', [
            'warehouse_id' => $this->hqWarehouse->id,
        ]);
        $this->assertDatabaseCount('cash_transactions', 0);
    }

    public function test_branch_admin_sees_only_own_bank_reconciliation_sessions(): void
    {
        $ownSession = BankReconciliation::factory()->create([
            'warehouse_id' => $this->branchWarehouse->id,
        ]);
        $foreignSession = BankReconciliation::factory()->create([
            'warehouse_id' => $this->hqWarehouse->id,
        ]);

        $this->actingAs($this->branchAdmin);

        $component = new BankReconciliationList;
        $sessions = $component->sessions();

        $this->assertTrue($sessions->getCollection()
            ->every(fn ($session) => $session->warehouse_id === $this->branchWarehouse->id));
        $this->assertTrue($sessions->getCollection()->contains('id', $ownSession->id));
        $this->assertFalse($sessions->getCollection()->contains('id', $foreignSession->id));
    }

    public function test_branch_admin_cannot_open_bank_reconciliation_session_of_other_branch(): void
    {
        $foreignSession = BankReconciliation::factory()->create([
            'warehouse_id' => $this->hqWarehouse->id,
        ]);

        $this->actingAs($this->branchAdmin);

        $component = new BankReconciliationList;
        $component->openSessionId = $foreignSession->id;

        $this->expectException(HttpException::class);
        $component->openSession();
    }

    public function test_unrestricted_user_still_sees_all_warehouses(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(StockList::class)
            ->assertViewHas('warehouses', fn ($options) => $options->count() === 2);
    }
}
