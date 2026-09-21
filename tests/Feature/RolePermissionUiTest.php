<?php

namespace Tests\Feature;

use App\Livewire\RolePermissionList;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RolePermissionUiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('name', 'super_admin')->first());
    }

    public function test_admin_with_manage_rbac_can_open_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('role-permissions.index'))
            ->assertOk();
    }

    public function test_user_without_manage_rbac_is_forbidden(): void
    {
        $kasir = User::factory()->create();
        $kasir->roles()->attach(Role::where('name', 'kasir')->first());

        $this->actingAs($kasir)
            ->get(route('role-permissions.index'))
            ->assertForbidden();
    }

    public function test_permissions_are_grouped_by_module(): void
    {
        $component = Livewire::actingAs($this->admin)->test(RolePermissionList::class);

        $grouped = $component->instance()->groupedPermissions;

        $this->assertArrayHasKey('master-data', $grouped);
        $this->assertArrayHasKey('hr', $grouped);
        $this->assertSame(
            'Kelola absensi, shift, lembur, dan cuti',
            $grouped['hr']->firstWhere('name', 'manage-attendance')->description
        );
    }

    public function test_selecting_role_loads_its_checked_permissions(): void
    {
        $role = Role::where('name', 'kasir')->first();

        Livewire::actingAs($this->admin)
            ->test(RolePermissionList::class)
            ->call('selectRole', $role->id)
            ->assertSet('selectedRoleId', $role->id)
            ->assertSet('checked', $role->permissions->pluck('id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_toggle_permission_updates_checked_list(): void
    {
        $role = Role::where('name', 'owner')->first();
        $permission = Permission::where('name', 'view-inventory')->first();

        $component = Livewire::actingAs($this->admin)
            ->test(RolePermissionList::class)
            ->call('selectRole', $role->id);

        $initialCount = count($component->get('checked'));

        $component->call('togglePermission', $permission->id)
            ->assertSet('checked', fn ($checked) => in_array($permission->id, $checked, true));

        $component->call('togglePermission', $permission->id)
            ->assertSet('checked', fn ($checked) => ! in_array($permission->id, $checked, true));
    }

    public function test_toggle_group_selects_and_deselects_whole_module(): void
    {
        $role = Role::where('name', 'owner')->first();
        $inventoryIds = Permission::where('group', 'inventory')->pluck('id')->map(fn ($id) => (int) $id)->all();

        $component = Livewire::actingAs($this->admin)
            ->test(RolePermissionList::class)
            ->call('selectRole', $role->id);

        // Pilih semua modul inventory
        $component->call('toggleGroup', 'inventory');
        $checked = $component->get('checked');
        foreach ($inventoryIds as $id) {
            $this->assertContains($id, $checked);
        }

        // Batal semua modul inventory
        $component->call('toggleGroup', 'inventory');
        $checked = $component->get('checked');
        foreach ($inventoryIds as $id) {
            $this->assertNotContains($id, $checked);
        }
    }

    public function test_save_permissions_persists_to_database(): void
    {
        $role = Role::where('name', 'kasir')->first();
        $permission = Permission::where('name', 'manage-products')->first();

        Livewire::actingAs($this->admin)
            ->test(RolePermissionList::class)
            ->call('selectRole', $role->id)
            ->call('togglePermission', $permission->id)
            ->call('savePermissions')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('role_permission', [
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);

        // Kasir sekarang punya akses produk
        $user = User::factory()->create();
        $user->roles()->attach($role);
        $this->assertTrue($user->fresh()->hasPermission('manage-products'));
    }

    public function test_create_new_role_via_form(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RolePermissionList::class)
            ->set('roleName', 'staff_hr')
            ->set('roleDisplayName', 'Staff HR')
            ->set('roleDescription', 'Tim HR')
            ->call('saveRole')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('roles', ['name' => 'staff_hr', 'display_name' => 'Staff HR']);
    }

    public function test_duplicate_role_name_is_rejected(): void
    {
        Livewire::actingAs($this->admin)
            ->test(RolePermissionList::class)
            ->set('roleName', 'kasir')
            ->set('roleDisplayName', 'Kasir Duplikat')
            ->call('saveRole')
            ->assertHasErrors(['roleName']);
    }

    public function test_edit_existing_role(): void
    {
        $role = Role::where('name', 'kasir')->first();

        Livewire::actingAs($this->admin)
            ->test(RolePermissionList::class)
            ->call('editRole', $role->id)
            ->assertSet('editingRoleId', $role->id)
            ->assertSet('roleName', 'kasir')
            ->set('roleDisplayName', 'Kasir Toko')
            ->call('saveRole')
            ->assertHasNoErrors();

        $this->assertSame('Kasir Toko', $role->fresh()->display_name);
    }

    public function test_super_admin_role_cannot_be_deleted(): void
    {
        $superAdmin = Role::where('name', 'super_admin')->first();

        Livewire::actingAs($this->admin)
            ->test(RolePermissionList::class)
            ->call('deleteRole', $superAdmin->id);

        // super_admin tidak boleh terhapus
        $this->assertDatabaseHas('roles', ['id' => $superAdmin->id, 'deleted_at' => null]);

        $this->assertDatabaseHas('roles', ['id' => $superAdmin->id]);
    }

    public function test_role_in_use_cannot_be_deleted(): void
    {
        $role = Role::where('name', 'kasir')->first();
        $user = User::factory()->create();
        $user->roles()->attach($role);

        Livewire::actingAs($this->admin)
            ->test(RolePermissionList::class)
            ->call('deleteRole', $role->id);

        // role yang dipakai user tidak boleh terhapus
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'deleted_at' => null]);

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_unused_role_can_be_deleted(): void
    {
        $role = Role::create(['name' => 'hapus_saya', 'display_name' => 'Hapus Saya']);

        Livewire::actingAs($this->admin)
            ->test(RolePermissionList::class)
            ->call('deleteRole', $role->id)
            ->assertHasNoErrors();

        $this->assertSoftDeleted('roles', ['id' => $role->id]);
    }

    public function test_assign_role_to_user(): void
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'staff_gudang')->first();

        Livewire::actingAs($this->admin)
            ->test(RolePermissionList::class)
            ->set('assignUserId', $user->id)
            ->set('assignRoleId', $role->id)
            ->call('assignRole')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('role_user', ['user_id' => $user->id, 'role_id' => $role->id]);
        $this->assertTrue($user->fresh()->hasPermission('view-inventory'));
    }

    public function test_revoke_role_from_user(): void
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'staff_gudang')->first();
        $user->roles()->attach($role);

        Livewire::actingAs($this->admin)
            ->test(RolePermissionList::class)
            ->call('revokeRole', $user->id, $role->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('role_user', ['user_id' => $user->id, 'role_id' => $role->id]);
        $this->assertFalse($user->fresh()->hasPermission('view-inventory'));
    }

    public function test_last_super_admin_role_cannot_be_revoked(): void
    {
        $role = Role::where('name', 'super_admin')->first();

        Livewire::actingAs($this->admin)
            ->test(RolePermissionList::class)
            ->call('revokeRole', $this->admin->id, $role->id);

        // super_admin terakhir tidak boleh dilepas
        $this->assertDatabaseHas('role_user', ['user_id' => $this->admin->id, 'role_id' => $role->id]);

        $this->assertDatabaseHas('role_user', ['user_id' => $this->admin->id, 'role_id' => $role->id]);
    }
}
