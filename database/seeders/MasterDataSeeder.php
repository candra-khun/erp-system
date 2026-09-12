<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // Base units
        $pcs = ProductUnit::create(['name' => 'Pcs', 'symbol' => 'pcs', 'conversion_factor' => 1, 'is_base' => true]);
        $box = ProductUnit::create(['name' => 'Box', 'symbol' => 'box', 'conversion_factor' => 12, 'base_unit_id' => $pcs->id, 'is_base' => false]);
        $dus = ProductUnit::create(['name' => 'Dus', 'symbol' => 'dus', 'conversion_factor' => 48, 'base_unit_id' => $pcs->id, 'is_base' => false]);
        $kg = ProductUnit::create(['name' => 'Kilogram', 'symbol' => 'kg', 'conversion_factor' => 1, 'is_base' => true]);

        // Categories
        $makanan = ProductCategory::create(['name' => 'Makanan', 'slug' => 'makanan', 'sort_order' => 1]);
        $minuman = ProductCategory::create(['name' => 'Minuman', 'slug' => 'minuman', 'sort_order' => 2]);
        $snack = ProductCategory::create(['name' => 'Snack', 'slug' => 'snack', 'parent_id' => $makanan->id, 'sort_order' => 1]);
        $sembako = ProductCategory::create(['name' => 'Sembako', 'slug' => 'sembako', 'sort_order' => 3]);

        // Warehouses
        $whMain = Warehouse::create([
            'code' => 'WH-01',
            'name' => 'Gudang Pusat Jakarta',
            'type' => 'warehouse',
            'address' => 'Jl. Industri Raya No. 10, Jakarta',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'phone' => '021-1234567',
            'manager_name' => 'Budi Santoso',
        ]);
        $branchBandung = Warehouse::create([
            'code' => 'BR-01',
            'name' => 'Cabang Bandung',
            'type' => 'branch',
            'address' => 'Jl. Asia Afrika No. 25, Bandung',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'phone' => '022-7654321',
            'manager_name' => 'Siti Nurhaliza',
        ]);
        $storeSurabaya = Warehouse::create([
            'code' => 'ST-01',
            'name' => 'Toko Surabaya',
            'type' => 'store',
            'address' => 'Jl. Tunjungan No. 50, Surabaya',
            'city' => 'Surabaya',
            'province' => 'Jawa Timur',
            'phone' => '031-9876543',
            'manager_name' => 'Ahmad Dahlan',
        ]);

        // Suppliers
        $suppliers = Supplier::factory()->count(5)->create();

        // Customers
        Customer::factory()->count(10)->create();
        Customer::factory()->reseller()->count(3)->create();
        Customer::factory()->member()->count(5)->create();

        // Products
        $products = Product::factory()->count(20)->create([
            'product_category_id' => fake()->randomElement([$makanan->id, $minuman->id, $snack->id, $sembako->id]),
            'base_unit_id' => fake()->randomElement([$pcs->id, $box->id, $kg->id]),
        ]);

        // Stock for each product in main warehouse
        foreach ($products as $product) {
            Stock::create([
                'product_id' => $product->id,
                'warehouse_id' => $whMain->id,
                'quantity' => fake()->randomFloat(2, 50, 500),
            ]);
        }

        // Some stock in branch
        $branchProducts = $products->random(10);
        foreach ($branchProducts as $product) {
            Stock::create([
                'product_id' => $product->id,
                'warehouse_id' => $branchBandung->id,
                'quantity' => fake()->randomFloat(2, 10, 200),
            ]);
        }
    }
}
