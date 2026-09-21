<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Membuat satu akun login untuk setiap role beserta izinnya.
 *
 * Semua password sama: "password" (ganti di produksi).
 */
class RoleUserSeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, email: string}>
     */
    private array $accounts = [
        'super_admin' => ['name' => 'Super Admin ERP', 'email' => 'superadmin@erp.local'],
        'admin_cabang' => ['name' => 'Admin Cabang', 'email' => 'admin.cabang@erp.local'],
        'kasir' => ['name' => 'Kasir Toko', 'email' => 'kasir@erp.local'],
        'staff_gudang' => ['name' => 'Staff Gudang', 'email' => 'gudang@erp.local'],
        'staff_pembelian' => ['name' => 'Staff Pembelian', 'email' => 'pembelian@erp.local'],
        'staff_keuangan' => ['name' => 'Staff Keuangan', 'email' => 'keuangan@erp.local'],
        'sales_marketing' => ['name' => 'Sales Marketing', 'email' => 'sales@erp.local'],
        'owner' => ['name' => 'Owner', 'email' => 'owner@erp.local'],
    ];

    public function run(): void
    {
        $password = Hash::make('password');

        foreach ($this->accounts as $roleName => $credentials) {
            $role = Role::where('name', $roleName)->first();

            if ($role === null) {
                continue;
            }

            $user = User::firstOrCreate(
                ['email' => $credentials['email']],
                [
                    'name' => $credentials['name'],
                    'email_verified_at' => now(),
                    'password' => $password,
                ]
            );

            $user->roles()->syncWithoutDetaching([$role->id]);
        }

        $this->assignOrphanUsers();
    }

    /**
     * Pastikan tidak ada user yang tertinggal tanpa role sama sekali.
     * User tanpa role tidak bisa mengakses modul manapun.
     */
    private function assignOrphanUsers(): void
    {
        $fallbackRoles = Role::whereIn('name', ['staff_gudang', 'staff_pembelian', 'staff_keuangan'])
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($fallbackRoles === []) {
            return;
        }

        $orphans = User::whereDoesntHave('roles')->get();
        $position = 0;

        foreach ($orphans as $user) {
            $user->roles()->syncWithoutDetaching([$fallbackRoles[$position % count($fallbackRoles)]]);
            $position++;
        }
    }
}
