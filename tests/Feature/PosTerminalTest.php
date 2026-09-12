<?php

namespace Tests\Feature;

use App\Events\StockChanged;
use App\Livewire\PosTerminal;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class PosTerminalTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Warehouse $warehouse;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cashier = User::factory()->create();
        $kasirRole = Role::where('name', 'kasir')->first();
        $this->cashier->roles()->attach($kasirRole);

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
            'quantity' => 10,
        ]);
    }

    public function test_cashier_can_access_pos_page(): void
    {
        $response = $this->actingAs($this->cashier)->get('/pos');

        $response->assertOk();
    }

    public function test_user_without_pos_permission_cannot_access_pos_page(): void
    {
        $financeRole = Role::where('name', 'staff_keuangan')->first();
        $financeUser = User::factory()->create();
        $financeUser->roles()->attach($financeRole);

        $response = $this->actingAs($financeUser)->get('/pos');

        $response->assertForbidden();
    }

    public function test_pos_page_shows_warehouse_selector_and_payment_methods(): void
    {
        $response = $this->actingAs($this->cashier)->get('/pos');

        $response->assertOk()
            ->assertSee('SCAN BARCODE')
            ->assertSee($this->warehouse->name)
            ->assertSee('Tunai')
            ->assertSee('Uang Bayar');
    }

    public function test_checkout_via_component_deducts_stock(): void
    {
        Event::fake([StockChanged::class]);

        Livewire::actingAs($this->cashier)
            ->test(PosTerminal::class)
            ->set('warehouse_id', (string) $this->warehouse->id)
            ->call('addToCart', $this->product->id)
            ->set('payment_method', 'cash')
            ->set('paid_amount', '20000')
            ->call('checkout')
            ->assertHasNoErrors();

        Event::assertDispatched(StockChanged::class, fn ($e) => $e->type === 'out'
            && $e->quantity === 1.0
            && $e->warehouseId === $this->warehouse->id);

        $this->assertDatabaseCount('sales_transactions', 1);
        $this->assertDatabaseHas('sales_transactions', [
            'warehouse_id' => $this->warehouse->id,
            'payment_method' => 'cash',
            'total_amount' => 10000,
            'paid_amount' => 20000,
            'change_amount' => 10000,
        ]);
    }

    public function test_checkout_rejects_insufficient_payment_for_cash(): void
    {
        Livewire::actingAs($this->cashier)
            ->test(PosTerminal::class)
            ->set('warehouse_id', (string) $this->warehouse->id)
            ->call('addToCart', $this->product->id)
            ->set('payment_method', 'cash')
            ->set('paid_amount', '5000')
            ->call('checkout')
            ->assertHasErrors(['paid_amount']);
    }

    public function test_scan_add_finds_product_by_sku(): void
    {
        Livewire::actingAs($this->cashier)
            ->test(PosTerminal::class)
            ->set('search', $this->product->sku)
            ->call('scanAdd')
            ->assertSet('cart', fn ($cart) => count($cart) === 1 && $cart[0]['qty'] === 1);
    }

    public function test_checkout_fails_when_stock_insufficient(): void
    {
        // Quantity 99 vs stock 10
        Livewire::actingAs($this->cashier)
            ->test(PosTerminal::class)
            ->set('warehouse_id', (string) $this->warehouse->id)
            ->call('addToCart', $this->product->id)
            ->set('cart.0.qty', 99)
            ->set('payment_method', 'card')
            ->call('checkout');

        // Listener throws inside DB::transaction -> no transaction persisted
        $this->assertDatabaseCount('sales_transactions', 0);
    }
}
