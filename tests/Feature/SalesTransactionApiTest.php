<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesTransaction;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalesTransactionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();

        $permission = Permission::create(['name' => 'use-pos']);
        $role = Role::create(['name' => 'cashier']);
        $role->permissions()->attach($permission);
        $this->user->roles()->attach($role);

        Sanctum::actingAs($this->user);
    }

    public function test_can_list_sales_transactions(): void
    {
        SalesTransaction::factory()->count(3)->create();

        $response = $this->getJson('/api/sales-transactions');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_sales_transaction(): void
    {
        $warehouse = Warehouse::factory()->create();
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();

        // Ensure stock exists for the product in the warehouse
        Stock::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
        ]);

        $payload = [
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'payment_method' => 'cash',
            'paid_amount' => 100000,
            'notes' => 'Test transaction',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 10000,
                    'discount_amount' => 0,
                ],
            ],
        ];

        $response = $this->postJson('/api/sales-transactions', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.warehouse_id', $warehouse->id);

        $this->assertDatabaseHas('sales_transactions', [
            'warehouse_id' => $warehouse->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('sales_transaction_items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_can_show_sales_transaction(): void
    {
        $transaction = SalesTransaction::factory()->create();

        $response = $this->getJson("/api/sales-transactions/{$transaction->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $transaction->id);
    }

    public function test_can_void_completed_sales_transaction(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $transaction = SalesTransaction::factory()->create([
            'status' => 'completed',
            'warehouse_id' => $warehouse->id,
        ]);

        $transaction->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 10000,
            'discount_amount' => 0,
            'subtotal' => 20000,
        ]);

        $response = $this->postJson("/api/sales-transactions/{$transaction->id}/void");

        $response->assertOk()
            ->assertJsonPath('data.status', 'voided');

        $this->assertDatabaseHas('sales_transactions', [
            'id' => $transaction->id,
            'status' => 'voided',
        ]);
    }

    public function test_cannot_void_already_voided_transaction(): void
    {
        $transaction = SalesTransaction::factory()->create([
            'status' => 'voided',
        ]);

        $response = $this->postJson("/api/sales-transactions/{$transaction->id}/void");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Transaction is already voided.');
    }

    public function test_cannot_void_non_completed_transaction(): void
    {
        $transaction = SalesTransaction::factory()->create([
            'status' => 'refunded',
        ]);

        $response = $this->postJson("/api/sales-transactions/{$transaction->id}/void");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Only completed transactions can be voided.');
    }

    public function test_create_sales_transaction_validates_required_fields(): void
    {
        $response = $this->postJson('/api/sales-transactions', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['warehouse_id', 'payment_method', 'items', 'paid_amount']);
    }

    public function test_unauthenticated_user_cannot_access_sales_transactions(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/sales-transactions')->assertUnauthorized();
    }
}
