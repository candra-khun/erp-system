<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $permission = Permission::create([
            'name' => 'manage-suppliers',
            'display_name' => 'Manage Suppliers',
            'group' => 'master',
        ]);

        $role = Role::create([
            'name' => 'admin',
            'display_name' => 'Admin',
        ]);

        $role->permissions()->attach($permission);
        $this->user->roles()->attach($role);
    }

    public function test_can_list_suppliers(): void
    {
        Supplier::factory()->count(3)->create();

        $response = $this->actingAs($this->user)->getJson('/api/suppliers');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_supplier(): void
    {
        $data = [
            'code' => 'SUP-999',
            'name' => 'Test Supplier',
            'contact_person' => 'John Doe',
            'phone' => '081234567890',
            'email' => 'supplier@test.com',
            'address' => 'Jl. Test No. 1',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'postal_code' => '12345',
            'payment_terms_days' => 30,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/suppliers', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('suppliers', ['code' => 'SUP-999', 'name' => 'Test Supplier']);
    }

    public function test_can_show_supplier(): void
    {
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($this->user)->getJson("/api/suppliers/{$supplier->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $supplier->id);
    }

    public function test_can_update_supplier(): void
    {
        $supplier = Supplier::factory()->create();

        $updateData = [
            'name' => 'Updated Supplier Name',
            'contact_person' => 'Jane Doe',
        ];

        $response = $this->actingAs($this->user)->putJson("/api/suppliers/{$supplier->id}", $updateData);

        $response->assertStatus(200);
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => 'Updated Supplier Name']);
    }

    public function test_can_delete_supplier(): void
    {
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($this->user)->deleteJson("/api/suppliers/{$supplier->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
    }

    public function test_unauthenticated_user_cannot_access_suppliers(): void
    {
        Supplier::factory()->create();

        $this->getJson('/api/suppliers')->assertStatus(401);
        $this->postJson('/api/suppliers', [])->assertStatus(401);
        $this->getJson('/api/suppliers/1')->assertStatus(401);
        $this->putJson('/api/suppliers/1', [])->assertStatus(401);
        $this->deleteJson('/api/suppliers/1')->assertStatus(401);
    }
}
