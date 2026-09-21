<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Admin user
        User::factory()->create([
            'name' => 'Admin ERP',
            'email' => 'admin@erp.local',
        ]);

        // Staff users
        User::factory()->count(5)->create();

        $this->call([
            MasterDataSeeder::class,
            RolesAndPermissionsSeeder::class,
            RoleUserSeeder::class,
            ChartOfAccountsSeeder::class,
        ]);
    }
}
