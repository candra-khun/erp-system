<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();

        $permission = Permission::create(['name' => 'manage-warehouses']);
        $role = Role::create(['name' => 'admin']);
        $role->permissions()->attach($permission);
        $this->user->roles()->attach($role);
    }

    public function test_can_list_warehouses(): void
    {
        Warehouse::factory()->count(3)->create();

        $response = $this->actingAs($this->user)->getJson('/api/warehouses');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_warehouse(): void
    {
        $data = [
            'code' => 'WH-99',
            'name' => 'Test Warehouse',
            'type' => 'warehouse',
            'address' => 'Jl. Test No. 1',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'phone' => '081234567890',
            'manager_name' => 'John Doe',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/warehouses', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('warehouses', ['code' => 'WH-99', 'name' => 'Test Warehouse']);
    }

    public function test_can_show_warehouse(): void
    {
        $warehouse = Warehouse::factory()->create();

        $response = $this->actingAs($this->user)->getJson("/api/warehouses/{$warehouse->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $warehouse->id);
    }

    public function test_can_update_warehouse(): void
    {
        $warehouse = Warehouse::factory()->create();

        $updateData = [
            'name' => 'Updated Warehouse Name',
            'manager_name' => 'Jane Doe',
        ];

        $response = $this->actingAs($this->user)->putJson("/api/warehouses/{$warehouse->id}", $updateData);

        $response->assertStatus(200);
        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id, 'name' => 'Updated Warehouse Name']);
    }

    public function test_can_delete_warehouse(): void
    {
        $warehouse = Warehouse::factory()->create();

        $response = $this->actingAs($this->user)->deleteJson("/api/warehouses/{$warehouse->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('warehouses', ['id' => $warehouse->id]);
    }

    public function test_unauthenticated_user_cannot_access_warehouses(): void
    {
        Warehouse::factory()->create();

        $this->getJson('/api/warehouses')->assertStatus(401);
        $this->postJson('/api/warehouses', [])->assertStatus(401);
        $this->getJson('/api/warehouses/1')->assertStatus(401);
        $this->putJson('/api/warehouses/1', [])->assertStatus(401);
        $this->deleteJson('/api/warehouses/1')->assertStatus(401);
    }
}
