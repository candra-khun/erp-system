<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SalesOrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Warehouse $warehouse;

    private Product $product;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->user = User::factory()->create();
        $salesRole = Role::where('name', 'sales_marketing')->first();
        $this->user->roles()->attach($salesRole);

        $this->warehouse = Warehouse::factory()->create();
        $category = ProductCategory::factory()->create();
        $unit = ProductUnit::factory()->create();
        $this->customer = Customer::factory()->create();

        $this->product = Product::factory()->create([
            'product_category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'selling_price' => 5000,
        ]);

        Stock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
        ]);
    }

    public function test_confirm_deducts_stock(): void
    {
        $service = app(SalesOrderService::class);

        $salesOrder = SalesOrder::create([
            'so_number' => 'SO-TEST-0001',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => now()->toDateString(),
            'status' => 'draft',
            'subtotal' => 25000,
            'total_amount' => 25000,
        ]);

        $salesOrder->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_price' => 5000,
            'discount_amount' => 0,
            'subtotal' => 25000,
        ]);

        $service->confirm($salesOrder, $this->user->id);

        $this->assertSame('confirmed', $salesOrder->fresh()->status);
        $this->assertEquals(95, (float) Stock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)->value('quantity'));

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'out',
            'quantity' => 5,
        ]);
    }

    public function test_cancel_after_confirm_restores_stock(): void
    {
        $service = app(SalesOrderService::class);

        $salesOrder = SalesOrder::create([
            'so_number' => 'SO-TEST-0002',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => now()->toDateString(),
            'status' => 'draft',
            'subtotal' => 25000,
            'total_amount' => 25000,
        ]);

        $salesOrder->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_price' => 5000,
            'discount_amount' => 0,
            'subtotal' => 25000,
        ]);

        $service->confirm($salesOrder, $this->user->id);
        $service->cancel($salesOrder->fresh(), $this->user->id);

        $this->assertSame('cancelled', $salesOrder->fresh()->status);
        $this->assertEquals(100, (float) Stock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)->value('quantity'));
    }

    public function test_api_confirm_endpoint_deducts_stock(): void
    {
        $salesOrder = SalesOrder::create([
            'so_number' => 'SO-TEST-0003',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => now()->toDateString(),
            'status' => 'draft',
            'subtotal' => 10000,
            'total_amount' => 10000,
            'created_by' => $this->user->id,
        ]);

        $salesOrder->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price' => 5000,
            'discount_amount' => 0,
            'subtotal' => 10000,
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/sales-orders/{$salesOrder->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertEquals(98, (float) Stock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)->value('quantity'));
    }

    public function test_confirm_non_draft_returns_422(): void
    {
        $salesOrder = SalesOrder::create([
            'so_number' => 'SO-TEST-0004',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => now()->toDateString(),
            'status' => 'confirmed',
            'subtotal' => 10000,
            'total_amount' => 10000,
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/sales-orders/{$salesOrder->id}/confirm")
            ->assertStatus(422);
    }
}
