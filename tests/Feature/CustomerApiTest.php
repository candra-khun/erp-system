<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $permission = Permission::create([
            'name' => 'manage-customers',
            'display_name' => 'Manage Customers',
            'group' => 'master',
        ]);

        $role = Role::create([
            'name' => 'admin',
            'display_name' => 'Admin',
        ]);

        $role->permissions()->attach($permission);
        $this->user->roles()->attach($role);
    }

    public function test_can_list_customers(): void
    {
        Customer::factory()->count(3)->create();

        $response = $this->actingAs($this->user)->getJson('/api/customers');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_customer(): void
    {
        $data = [
            'code' => 'CUST-999',
            'name' => 'Test Customer',
            'type' => 'general',
            'contact_person' => 'John Doe',
            'phone' => '081234567890',
            'email' => 'customer@test.com',
            'address' => 'Jl. Test No. 1',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'postal_code' => '12345',
            'credit_limit' => 1000000,
            'payment_terms_days' => 30,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/customers', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('customers', ['code' => 'CUST-999', 'name' => 'Test Customer']);
    }

    public function test_can_show_customer(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($this->user)->getJson("/api/customers/{$customer->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $customer->id);
    }

    public function test_can_update_customer(): void
    {
        $customer = Customer::factory()->create();

        $updateData = [
            'name' => 'Updated Customer Name',
            'contact_person' => 'Jane Doe',
        ];

        $response = $this->actingAs($this->user)->putJson("/api/customers/{$customer->id}", $updateData);

        $response->assertStatus(200);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Updated Customer Name']);
    }

    public function test_can_delete_customer(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($this->user)->deleteJson("/api/customers/{$customer->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_unauthenticated_user_cannot_access_customers(): void
    {
        Customer::factory()->create();

        $this->getJson('/api/customers')->assertStatus(401);
        $this->postJson('/api/customers', [])->assertStatus(401);
        $this->getJson('/api/customers/1')->assertStatus(401);
        $this->putJson('/api/customers/1', [])->assertStatus(401);
        $this->deleteJson('/api/customers/1')->assertStatus(401);
    }
}
