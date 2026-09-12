<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Permission registry grouped by module.
     *
     * @var array<string, list<string>>
     */
    private array $permissions = [
        'master-data' => [
            'manage-products',
            'manage-suppliers',
            'manage-customers',
            'manage-warehouses',
        ],
        'inventory' => [
            'view-inventory',
            'manage-transfers',
            'manage-opnames',
        ],
        'purchasing' => [
            'manage-purchases',
        ],
        'sales' => [
            'manage-sales',
            'use-pos',
        ],
        'finance' => [
            'manage-finance',
        ],
        'system' => [
            'view-dashboard',
        ],
    ];

    /**
     * Role → permission matrix.
     *
     * @var array<string, list<string>>
     */
    private array $roleMatrix = [
        'super_admin' => ['*'],
        'admin_cabang' => [
            'manage-products', 'manage-suppliers', 'manage-customers', 'manage-warehouses',
            'view-inventory', 'manage-transfers', 'manage-opnames',
            'manage-purchases', 'manage-sales', 'use-pos', 'view-dashboard',
        ],
        'kasir' => ['use-pos', 'view-dashboard'],
        'staff_gudang' => ['view-inventory', 'manage-transfers', 'manage-opnames', 'view-dashboard'],
        'staff_pembelian' => ['manage-purchases', 'manage-suppliers', 'view-inventory', 'view-dashboard'],
        'staff_keuangan' => ['manage-finance', 'view-dashboard'],
        'sales_marketing' => ['manage-sales', 'manage-customers', 'view-dashboard'],
        'owner' => ['view-dashboard'],
    ];

    public function run(): void
    {
        // Create all permissions
        $created = [];
        foreach ($this->permissions as $group => $names) {
            foreach ($names as $name) {
                $created[$name] = Permission::firstOrCreate(
                    ['name' => $name],
                    ['description' => 'Akses modul '.$group]
                );
            }
        }

        // Create roles and attach permissions
        foreach ($this->roleMatrix as $roleName => $permissionNames) {
            $role = Role::firstOrCreate(
                ['name' => $roleName],
                ['display_name' => ucwords(str_replace('_', ' ', $roleName))]
            );

            $permissionIds = $permissionNames === ['*']
                ? array_column($created, 'id')
                : collect($created)->filter(fn ($p, $name) => in_array($name, $permissionNames, true))->pluck('id')->all();

            $role->permissions()->syncWithoutDetaching($permissionIds);
        }

        // Assign super_admin to the default admin user
        $admin = User::where('email', 'admin@erp.local')->first();
        if ($admin) {
            $superAdmin = Role::where('name', 'super_admin')->first();
            if ($superAdmin) {
                $admin->roles()->syncWithoutDetaching([$superAdmin->id]);
            }
        }
    }
}
