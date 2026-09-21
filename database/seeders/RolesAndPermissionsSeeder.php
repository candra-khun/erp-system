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
     * @var array<string, array<string, string>>
     */
    private array $permissions = [
        'master-data' => [
            'manage-products' => 'Kelola produk, kategori, dan satuan',
            'manage-suppliers' => 'Kelola data pemasok',
            'manage-customers' => 'Kelola data pelanggan',
            'manage-warehouses' => 'Kelola cabang/gudang',
        ],
        'inventory' => [
            'view-inventory' => 'Lihat stok dan kartu stok',
            'manage-transfers' => 'Kelola transfer stok antar cabang',
            'manage-opnames' => 'Kelola stock opname',
        ],
        'purchasing' => [
            'manage-purchases' => 'Kelola purchase order dan penerimaan barang',
        ],
        'sales' => [
            'manage-sales' => 'Kelola sales order dan retur penjualan',
            'use-pos' => 'Akses kasir/POS',
            'manage-logistics' => 'Kelola pengiriman dan surat jalan',
        ],
        'finance' => [
            'manage-finance' => 'Kelola kas, jurnal, dan laporan keuangan',
            'manage-payroll' => 'Kelola penggajian karyawan',
        ],
        'hr' => [
            'manage-employees' => 'Kelola data karyawan',
            'manage-attendance' => 'Kelola absensi, shift, lembur, dan cuti',
            'view-attendance' => 'Lihat rekap absensi (tanpa ubah)',
        ],
        'integrations' => [
            'manage-marketplace' => 'Kelola channel marketplace',
            'manage-gateway' => 'Kelola payment gateway',
            'manage-consignment' => 'Kelola konsinyasi dan settlement',
        ],
        'system' => [
            'manage-rbac' => 'Kelola role, permission, dan user',
            'view-dashboard' => 'Akses dashboard utama',
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
            'manage-employees', 'manage-consignment', 'manage-attendance', 'view-attendance',
        ],
        'kasir' => ['use-pos', 'view-dashboard'],
        'staff_gudang' => ['view-inventory', 'manage-transfers', 'manage-opnames', 'view-dashboard'],
        'staff_pembelian' => ['manage-purchases', 'manage-suppliers', 'view-inventory', 'view-dashboard'],
        'staff_keuangan' => ['manage-finance', 'manage-payroll', 'view-dashboard'],
        'sales_marketing' => ['manage-sales', 'manage-customers', 'manage-logistics', 'manage-marketplace', 'view-dashboard'],
        'owner' => ['view-dashboard', 'view-attendance'],
    ];

    public function run(): void
    {
        // Create all permissions
        $created = [];
        foreach ($this->permissions as $group => $items) {
            foreach ($items as $name => $description) {
                $created[$name] = Permission::updateOrCreate(
                    ['name' => $name],
                    [
                        'display_name' => ucwords(str_replace('-', ' ', $name)),
                        'group' => $group,
                        'description' => $description,
                    ]
                );
            }
        }

        // Hapus permission lama yang sudah tidak dipakai dari registry
        // (hanya yang belum melekat ke role manapun).
        Permission::whereNotIn('name', array_keys($created))
            ->whereDoesntHave('roles')
            ->delete();

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
