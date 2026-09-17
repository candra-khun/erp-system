<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacAndAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_all_roles_and_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(8, Role::count());
        $this->assertSame(18, Permission::count());

        $superAdmin = Role::where('name', 'super_admin')->first();
        $this->assertSame(18, $superAdmin->permissions()->count());

        $kasir = Role::where('name', 'kasir')->first();
        $this->assertSame(2, $kasir->permissions()->count());
        $this->assertTrue($kasir->permissions->contains('name', 'use-pos'));
        $this->assertTrue($kasir->permissions->contains('name', 'view-dashboard'));

        $owner = Role::where('name', 'owner')->first();
        $this->assertSame(1, $owner->permissions()->count());
    }

    public function test_seeder_assigns_super_admin_to_admin_user(): void
    {
        $admin = User::factory()->create(['email' => 'admin@erp.local']);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertTrue($admin->fresh()->hasRole('super_admin'));
        $this->assertTrue($admin->fresh()->hasPermission('manage-products'));
        $this->assertTrue($admin->fresh()->hasPermission('manage-finance'));
    }

    public function test_product_price_change_is_audited(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $category = ProductCategory::factory()->create();
        $unit = ProductUnit::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'selling_price' => 10000,
        ]);

        $product->update(['selling_price' => 12500]);

        $log = AuditLog::where('auditable_type', Product::class)
            ->where('auditable_id', $product->id)
            ->where('action', 'price_changed')
            ->first();

        $this->assertNotNull($log, 'Audit log untuk perubahan harga tidak dibuat.');
        $this->assertEquals(10000, $log->old_values['selling_price']);
        $this->assertEquals(12500, $log->new_values['selling_price']);
        $this->assertEquals($user->id, $log->user_id);
    }

    public function test_product_creation_is_audited(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $category = ProductCategory::factory()->create();
        $unit = ProductUnit::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'base_unit_id' => $unit->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Product::class,
            'auditable_id' => $product->id,
            'action' => 'created',
            'user_id' => $user->id,
        ]);
    }

    public function test_web_route_blocks_user_without_permission(): void
    {
        $user = User::factory()->create();
        $this->seed(RolesAndPermissionsSeeder::class);
        // user has no roles

        $response = $this->actingAs($user)->get('/products');

        $response->assertForbidden();
    }

    public function test_web_route_allows_user_with_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'admin_cabang')->first());

        $response = $this->actingAs($user)->get('/products');

        $response->assertOk();
    }
}
