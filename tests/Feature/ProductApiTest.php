<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();

        $permission = Permission::create(['name' => 'manage-products']);
        $role = Role::create(['name' => 'admin']);
        $role->permissions()->attach($permission);
        $this->user->roles()->attach($role);
    }

    public function test_can_list_products(): void
    {
        Product::factory(3)->create();

        $response = $this->actingAs($this->user)->getJson('/api/products');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_product(): void
    {
        $category = ProductCategory::factory()->create();
        $unit = ProductUnit::factory()->create();

        $payload = [
            'sku' => 'TEST-001',
            'name' => 'Test Product',
            'product_category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'purchase_price' => 10000,
            'selling_price' => 15000,
            'min_stock' => 10,
        ];

        $response = $this->actingAs($this->user)->postJson('/api/products', $payload);

        $response->assertCreated()
            ->assertJsonFragment(['sku' => 'TEST-001', 'name' => 'Test Product']);

        $this->assertDatabaseHas('products', ['sku' => 'TEST-001']);
    }

    public function test_can_show_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->user)->getJson("/api/products/{$product->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $product->id]);
    }

    public function test_can_update_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->user)->putJson("/api/products/{$product->id}", [
            'sku' => $product->sku,
            'name' => 'Updated Name',
            'purchase_price' => 12000,
            'selling_price' => 18000,
        ]);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name']);
    }

    public function test_can_delete_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->user)->deleteJson("/api/products/{$product->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_unauthenticated_user_cannot_access_products(): void
    {
        $response = $this->getJson('/api/products');

        $response->assertUnauthorized();
    }

    public function test_can_search_products_by_name(): void
    {
        Product::factory()->create(['name' => 'Kopi Arabika']);
        Product::factory()->create(['name' => 'Teh Hijau']);

        $response = $this->actingAs($this->user)->getJson('/api/products?search=Kopi');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
