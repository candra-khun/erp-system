<?php

namespace Tests\Feature;

use App\Livewire\PosTerminal;
use App\Livewire\SalesOrderList;
use App\Livewire\StockTransferList;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MultiUnitConversionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Warehouse $warehouse;

    private Product $product;

    private ProductUnit $baseUnit;

    private ProductUnit $dusUnit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->user = User::factory()->create();
        $gudangRole = Role::where('name', 'staff_gudang')->first();
        $this->user->roles()->attach($gudangRole);

        $this->warehouse = Warehouse::factory()->create();

        $category = ProductCategory::factory()->create();
        $this->baseUnit = ProductUnit::factory()->create(['name' => 'Pcs', 'symbol' => 'pcs', 'is_base' => true]);
        $this->dusUnit = ProductUnit::factory()->derived($this->baseUnit, 12)->create(['name' => 'Dus', 'symbol' => 'dus']);

        $this->product = Product::factory()->create([
            'product_category_id' => $category->id,
            'base_unit_id' => $this->baseUnit->id,
            'selling_price' => 5000,
        ]);

        Stock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
        ]);
    }

    public function test_pos_cart_item_defaults_to_base_unit(): void
    {
        Livewire::actingAs($this->user)
            ->test(PosTerminal::class)
            ->call('addToCart', $this->product->id)
            ->assertSet('cart', fn ($cart) => count($cart) === 1
                && $cart[0]['unit_id'] === $this->baseUnit->id
                && $cart[0]['unit_symbol'] === 'pcs'
                && $cart[0]['unit_factor'] === 1.0
                && $cart[0]['price'] === 5000.0);
    }

    public function test_pos_switching_unit_updates_price_per_unit(): void
    {
        Livewire::actingAs($this->user)
            ->test(PosTerminal::class)
            ->call('addToCart', $this->product->id)
            ->call('updateUnit', 0, $this->dusUnit->id)
            ->assertSet('cart', fn ($cart) => count($cart) === 1
                && $cart[0]['unit_id'] === $this->dusUnit->id
                && $cart[0]['unit_symbol'] === 'dus'
                && $cart[0]['unit_factor'] === 12.0
                && $cart[0]['price'] === 60000.0); // 5000 x 12
    }

    public function test_pos_checkout_in_dus_sells_base_quantity(): void
    {
        Livewire::actingAs($this->user)
            ->test(PosTerminal::class)
            ->set('warehouse_id', (string) $this->warehouse->id)
            ->call('addToCart', $this->product->id)
            ->call('updateUnit', 0, $this->dusUnit->id)
            ->set('cart.0.qty', 2) // 2 dus
            ->set('payment_method', 'cash')
            ->set('paid_amount', '150000')
            ->call('checkout')
            ->assertHasNoErrors();

        // Item tersimpan dalam satuan dasar: 2 dus = 24 pcs
        $this->assertDatabaseHas('sales_transaction_items', [
            'product_id' => $this->product->id,
            'quantity' => 24,
            'unit_price' => 5000, // harga per pcs
            'subtotal' => 120000,  // 2 x 60.000
        ]);

        $this->assertDatabaseHas('stocks', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 76, // 100 - 24
        ]);
    }

    public function test_pos_adding_same_product_in_different_units_creates_separate_lines(): void
    {
        Livewire::actingAs($this->user)
            ->test(PosTerminal::class)
            ->call('addToCart', $this->product->id)
            ->call('updateUnit', 0, $this->dusUnit->id)
            ->call('addToCart', $this->product->id) // pcs line
            ->assertSet('cart', fn ($cart) => count($cart) === 2
                && $cart[0]['unit_id'] === $this->dusUnit->id
                && $cart[1]['unit_id'] === $this->baseUnit->id);
    }

    public function test_transfer_created_in_dus_moves_base_quantity(): void
    {
        $destination = Warehouse::factory()->create();

        Livewire::actingAs($this->user)
            ->test(StockTransferList::class)
            ->set('source_warehouse_id', (string) $this->warehouse->id)
            ->set('destination_warehouse_id', (string) $destination->id)
            ->set('items.0.product_id', (string) $this->product->id)
            ->set('items.0.unit_id', (string) $this->dusUnit->id)
            ->set('items.0.quantity', 2) // 2 dus
            ->call('store')
            ->assertHasNoErrors();

        // Item transfer tersimpan dalam satuan dasar: 24 pcs
        $this->assertDatabaseHas('stock_transfer_items', [
            'product_id' => $this->product->id,
            'quantity' => 24,
        ]);
    }

    public function test_transfer_without_unit_uses_quantity_as_is(): void
    {
        $destination = Warehouse::factory()->create();

        Livewire::actingAs($this->user)
            ->test(StockTransferList::class)
            ->set('source_warehouse_id', (string) $this->warehouse->id)
            ->set('destination_warehouse_id', (string) $destination->id)
            ->set('items.0.product_id', (string) $this->product->id)
            ->set('items.0.unit_id', '')
            ->set('items.0.quantity', 5)
            ->call('store')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('stock_transfer_items', [
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);
    }

    public function test_sales_order_created_in_dus_converts_qty_and_price(): void
    {
        $customer = Customer::factory()->create();

        Livewire::actingAs($this->user)
            ->test(SalesOrderList::class)
            ->call('openCreate')
            ->set('customer_id', (string) $customer->id)
            ->set('warehouse_id', (string) $this->warehouse->id)
            ->set('items.0.product_id', (string) $this->product->id)
            ->set('items.0.unit_id', (string) $this->dusUnit->id)
            ->set('items.0.quantity', 2)
            ->set('items.0.unit_price', 60000) // harga per dus
            ->call('store')
            ->assertHasNoErrors();

        // Item SO tersimpan dalam satuan dasar: 24 pcs @ Rp5.000
        $this->assertDatabaseHas('sales_order_items', [
            'product_id' => $this->product->id,
            'quantity' => 24,
            'unit_price' => 5000,
            'subtotal' => 120000,
        ]);
    }

    public function test_sales_order_without_unit_keeps_values(): void
    {
        $customer = Customer::factory()->create();

        Livewire::actingAs($this->user)
            ->test(SalesOrderList::class)
            ->call('openCreate')
            ->set('customer_id', (string) $customer->id)
            ->set('warehouse_id', (string) $this->warehouse->id)
            ->set('items.0.product_id', (string) $this->product->id)
            ->set('items.0.unit_id', '')
            ->set('items.0.quantity', 3)
            ->set('items.0.unit_price', 5000)
            ->call('store')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sales_order_items', [
            'product_id' => $this->product->id,
            'quantity' => 3,
            'unit_price' => 5000,
        ]);
    }
}
