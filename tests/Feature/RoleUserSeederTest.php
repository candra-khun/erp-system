<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\RoleUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_one_account_per_role(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RoleUserSeeder::class);

        foreach ([
            'super_admin' => 'superadmin@erp.local',
            'admin_cabang' => 'admin.cabang@erp.local',
            'kasir' => 'kasir@erp.local',
            'staff_gudang' => 'gudang@erp.local',
            'staff_pembelian' => 'pembelian@erp.local',
            'staff_keuangan' => 'keuangan@erp.local',
            'sales_marketing' => 'sales@erp.local',
            'owner' => 'owner@erp.local',
        ] as $roleName => $email) {
            $user = User::where('email', $email)->first();

            $this->assertNotNull($user, "Akun untuk role {$roleName} tidak ditemukan.");
            $this->assertTrue($user->hasRole($roleName), "Akun {$email} tidak memiliki role {$roleName}.");
        }
    }

    public function test_super_admin_has_all_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RoleUserSeeder::class);

        $superAdmin = User::where('email', 'superadmin@erp.local')->first();

        $this->assertSame(21, $superAdmin->permissions->count());
        $this->assertTrue($superAdmin->hasPermission('manage-attendance'));
        $this->assertTrue($superAdmin->hasPermission('view-attendance'));
    }

    public function test_kasir_only_has_pos_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RoleUserSeeder::class);

        $kasir = User::where('email', 'kasir@erp.local')->first();

        $this->assertTrue($kasir->hasPermission('use-pos'));
        $this->assertTrue($kasir->hasPermission('view-dashboard'));
        $this->assertFalse($kasir->hasPermission('manage-attendance'));
        $this->assertFalse($kasir->hasPermission('manage-payroll'));
    }

    public function test_staff_keuangan_has_payroll_but_not_attendance_management(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RoleUserSeeder::class);

        $keuangan = User::where('email', 'keuangan@erp.local')->first();

        $this->assertTrue($keuangan->hasPermission('manage-finance'));
        $this->assertTrue($keuangan->hasPermission('manage-payroll'));
        $this->assertFalse($keuangan->hasPermission('manage-attendance'));
    }

    public function test_owner_only_sees_dashboard(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RoleUserSeeder::class);

        $owner = User::where('email', 'owner@erp.local')->first();

        $this->assertSame(2, $owner->permissions->count());
        $this->assertTrue($owner->hasPermission('view-dashboard'));
        $this->assertFalse($owner->hasPermission('manage-employees'));
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->seed(RoleUserSeeder::class);
        $this->seed(RoleUserSeeder::class);

        $this->assertSame(1, User::where('email', 'kasir@erp.local')->count());
        $this->assertSame(1, User::where('email', 'kasir@erp.local')->first()->roles()->count());
    }

    public function test_seeder_assigns_role_to_orphan_users(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        // User tanpa role sama sekali (kasus user lama yang dibuat sebelum RBAC)
        $orphan = User::factory()->create(['email' => 'orphan@erp.local']);

        $this->seed(RoleUserSeeder::class);

        $this->assertGreaterThan(0, $orphan->fresh()->roles()->count(), 'User tanpa role harus diberi role fallback.');
        $this->assertGreaterThan(0, $orphan->fresh()->permissions->count());
    }

    public function test_every_role_account_can_access_dashboard(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RoleUserSeeder::class);

        foreach ([
            'superadmin@erp.local', 'admin.cabang@erp.local', 'kasir@erp.local',
            'gudang@erp.local', 'pembelian@erp.local', 'keuangan@erp.local',
            'sales@erp.local', 'owner@erp.local',
        ] as $email) {
            $this->actingAs(User::where('email', $email)->first())
                ->get(route('dashboard'))
                ->assertOk();
        }
    }
}
