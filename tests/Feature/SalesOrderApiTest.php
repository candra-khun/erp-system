<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalesOrderApiTest extends TestCase
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

    public function test_can_list_sales_orders(): void
    {
        SalesOrder::factory()->count(3)->create();

        $response = $this->getJson('/api/sales-orders');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_sales_order(): void
    {
        $customer = Customer::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $payload = [
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => now()->toDateString(),
            'delivery_date' => now()->addDays(7)->toDateString(),
            'notes' => 'Test sales order',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                    'unit_price' => 10000,
                    'discount_amount' => 0,
                ],
            ],
        ];

        $response = $this->postJson('/api/sales-orders', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.customer_id', $customer->id);

        $this->assertDatabaseHas('sales_orders', [
            'customer_id' => $customer->id,
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('sales_order_items', [
            'product_id' => $product->id,
            'quantity' => 5,
        ]);
    }

    public function test_can_show_sales_order(): void
    {
        $order = SalesOrder::factory()->create();

        $response = $this->getJson("/api/sales-orders/{$order->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $order->id);
    }

    public function test_can_update_draft_sales_order(): void
    {
        $order = SalesOrder::factory()->create(['status' => 'draft']);
        $newCustomer = Customer::factory()->create();

        $response = $this->putJson("/api/sales-orders/{$order->id}", [
            'customer_id' => $newCustomer->id,
            'notes' => 'Updated notes',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.notes', 'Updated notes');

        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'notes' => 'Updated notes',
        ]);
    }

    public function test_cannot_update_non_draft_sales_order(): void
    {
        $order = SalesOrder::factory()->create(['status' => 'confirmed']);

        $response = $this->putJson("/api/sales-orders/{$order->id}", [
            'notes' => 'Should fail',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Only draft sales orders can be updated.');
    }

    public function test_can_delete_draft_sales_order(): void
    {
        $order = SalesOrder::factory()->create(['status' => 'draft']);

        $response = $this->deleteJson("/api/sales-orders/{$order->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Sales order deleted successfully.');

        $this->assertSoftDeleted('sales_orders', ['id' => $order->id]);
    }

    public function test_cannot_delete_non_draft_sales_order(): void
    {
        $order = SalesOrder::factory()->create(['status' => 'confirmed']);

        $response = $this->deleteJson("/api/sales-orders/{$order->id}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Only draft sales orders can be deleted.');
    }

    public function test_can_confirm_draft_sales_order(): void
    {
        $order = SalesOrder::factory()->create(['status' => 'draft']);
        $response = $this->postJson("/api/sales-orders/{$order->id}/confirm");

        $response->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_cannot_confirm_non_draft_sales_order(): void
    {
        $order = SalesOrder::factory()->create(['status' => 'confirmed']);

        $response = $this->postJson("/api/sales-orders/{$order->id}/confirm");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Hanya sales order berstatus draft yang dapat dikonfirmasi. Status saat ini: confirmed');
    }

    public function test_can_cancel_sales_order(): void
    {
        $order = SalesOrder::factory()->create(['status' => 'draft']);

        $response = $this->postJson("/api/sales-orders/{$order->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_cannot_cancel_already_cancelled_sales_order(): void
    {
        $order = SalesOrder::factory()->create(['status' => 'cancelled']);

        $response = $this->postJson("/api/sales-orders/{$order->id}/cancel");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Sales order sudah dibatalkan.');
    }

    public function test_unauthenticated_user_cannot_access_sales_orders(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/sales-orders')->assertUnauthorized();
    }

    public function test_create_sales_order_validates_required_fields(): void
    {
        $response = $this->postJson('/api/sales-orders', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id', 'warehouse_id', 'order_date', 'items']);
    }
}
