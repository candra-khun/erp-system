<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesReturn;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalesReturnApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();

        $permission = Permission::create(['name' => 'manage-sales']);
        $role = Role::create(['name' => 'sales']);
        $role->permissions()->attach($permission);
        $this->user->roles()->attach($role);

        Sanctum::actingAs($this->user);
    }

    public function test_can_list_sales_returns(): void
    {
        SalesReturn::factory()->count(3)->create();

        $response = $this->getJson('/api/sales-returns');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_sales_return(): void
    {
        $customer = Customer::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $salesOrder = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'confirmed',
        ]);

        $payload = [
            'returnable_type' => 'sales_order',
            'returnable_id' => $salesOrder->id,
            'customer_id' => $customer->id,
            'return_date' => now()->toDateString(),
            'reason' => 'Defective item',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 10000,
                    'restock' => true,
                    'reason' => 'Damaged packaging',
                ],
            ],
        ];

        $response = $this->postJson('/api/sales-returns', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.customer_id', $customer->id);

        $this->assertDatabaseHas('sales_returns', [
            'customer_id' => $customer->id,
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('sales_return_items', [
            'product_id' => $product->id,
            'quantity' => 2,
            'restock' => true,
        ]);
    }

    public function test_can_show_sales_return(): void
    {
        $return = SalesReturn::factory()->create();

        $response = $this->getJson("/api/sales-returns/{$return->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $return->id);
    }

    public function test_can_approve_draft_sales_return(): void
    {
        $warehouse = Warehouse::factory()->create();
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $salesOrder = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
        ]);

        $return = SalesReturn::factory()->create([
            'status' => 'draft',
            'returnable_type' => SalesOrder::class,
            'returnable_id' => $salesOrder->id,
        ]);

        $return->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 10000,
            'subtotal' => 20000,
            'restock' => true,
        ]);

        $response = $this->postJson("/api/sales-returns/{$return->id}/approve");

        $response->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('sales_returns', [
            'id' => $return->id,
            'status' => 'approved',
        ]);

        // Restock should add stock back to the warehouse
        $this->assertDatabaseHas('stocks', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
        ]);
    }

    public function test_cannot_approve_non_draft_sales_return(): void
    {
        $return = SalesReturn::factory()->create(['status' => 'approved']);

        $response = $this->postJson("/api/sales-returns/{$return->id}/approve");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Only draft returns can be approved.');
    }

    public function test_can_cancel_draft_sales_return(): void
    {
        $return = SalesReturn::factory()->create(['status' => 'draft']);

        $response = $this->postJson("/api/sales-returns/{$return->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('sales_returns', [
            'id' => $return->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cannot_cancel_already_cancelled_sales_return(): void
    {
        $return = SalesReturn::factory()->create(['status' => 'cancelled']);

        $response = $this->postJson("/api/sales-returns/{$return->id}/cancel");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Return is already cancelled.');
    }

    public function test_cannot_cancel_approved_sales_return(): void
    {
        $return = SalesReturn::factory()->create(['status' => 'approved']);

        $response = $this->postJson("/api/sales-returns/{$return->id}/cancel");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Approved returns cannot be cancelled. Reverse the restock manually first.');
    }

    public function test_create_sales_return_validates_required_fields(): void
    {
        $response = $this->postJson('/api/sales-returns', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['returnable_type', 'returnable_id', 'customer_id', 'return_date', 'items']);
    }
}
