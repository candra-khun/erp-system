<?php

namespace Tests\Feature;

use App\Livewire\MasterDataImportPanel;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class MasterDataImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $superAdmin = Role::where('name', 'super_admin')->firstOrFail();
        $this->admin->roles()->attach($superAdmin);
    }

    /**
     * Build a CSV upload without relying on the GD extension.
     *
     * @param  list<list<string>>  $lines
     */
    private function csvUpload(array $lines, string $name = 'import.csv'): UploadedFile
    {
        $contents = '';

        foreach ($lines as $line) {
            $contents .= implode(',', $line)."\n";
        }

        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    public function test_products_can_be_imported_from_csv(): void
    {
        ProductCategory::factory()->create(['name' => 'Umum', 'slug' => 'umum']);
        ProductUnit::factory()->create(['name' => 'Pcs', 'symbol' => 'pcs']);

        $file = $this->csvUpload([
            ['sku', 'nama', 'kategori', 'satuan', 'barcode', 'harga_beli', 'harga_jual', 'stok_minimum', 'reorder_point', 'deskripsi', 'aktif'],
            ['IMP-001', 'Produk Import Satu', 'Elektronik', 'Pcs', '8991234567890', '10000', '15000', '5', '3', 'hasil import', '1'],
            ['IMP-002', 'Produk Import Dua', 'Elektronik', 'Pcs', '', '20000', '25000', '2', '1', '', '0'],
        ]);

        Livewire::actingAs($this->admin)
            ->test(MasterDataImportPanel::class)
            ->set('type', 'products')
            ->set('file', $file)
            ->call('import')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('products', [
            'sku' => 'IMP-001',
            'name' => 'Produk Import Satu',
            'barcode' => '8991234567890',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('products', [
            'sku' => 'IMP-002',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('product_categories', ['name' => 'Elektronik']);
    }

    public function test_product_import_updates_existing_sku(): void
    {
        $category = ProductCategory::factory()->create();
        $unit = ProductUnit::factory()->create();

        Product::factory()->create([
            'sku' => 'IMP-UPD',
            'name' => 'Nama Lama',
            'product_category_id' => $category->id,
            'base_unit_id' => $unit->id,
        ]);

        $file = $this->csvUpload([
            ['sku', 'nama', 'kategori', 'satuan', 'harga_beli', 'harga_jual'],
            ['IMP-UPD', 'Nama Baru', 'Umum', 'Pcs', '5000', '7000'],
        ]);

        Livewire::actingAs($this->admin)
            ->test(MasterDataImportPanel::class)
            ->set('type', 'products')
            ->set('file', $file)
            ->call('import')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('products', ['sku' => 'IMP-UPD', 'name' => 'Nama Baru']);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_product_import_reports_rows_missing_required_columns(): void
    {
        $file = $this->csvUpload([
            ['sku', 'nama'],
            ['IMP-OK', 'Produk Valid'],
            ['', 'Tanpa SKU'],
        ]);

        Livewire::actingAs($this->admin)
            ->test(MasterDataImportPanel::class)
            ->set('type', 'products')
            ->set('file', $file)
            ->call('import')
            ->assertHasNoErrors()
            ->assertSet('result.imported', 1)
            ->assertSet('result.errors', ['Baris 3: kolom SKU dan Nama wajib diisi.']);
    }

    public function test_suppliers_can_be_imported_from_csv(): void
    {
        $file = $this->csvUpload([
            ['kode', 'nama', 'kontak', 'telepon', 'email', 'alamat', 'kota', 'provinsi', 'kode_pos', 'termin_hari', 'aktif'],
            ['SUP-IMP', 'PT Import Supplier', 'Budi', '021-999', 'sup@import.id', 'Jl. Import 1', 'Jakarta', 'DKI', '12345', '30', '1'],
        ]);

        Livewire::actingAs($this->admin)
            ->test(MasterDataImportPanel::class)
            ->set('type', 'suppliers')
            ->set('file', $file)
            ->call('import')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('suppliers', [
            'code' => 'SUP-IMP',
            'name' => 'PT Import Supplier',
            'payment_terms_days' => 30,
        ]);
    }

    public function test_customers_can_be_imported_from_csv(): void
    {
        $file = $this->csvUpload([
            ['kode', 'nama', 'tipe', 'telepon', 'email', 'alamat', 'kota', 'provinsi', 'kode_pos', 'limit_kredit', 'termin_hari', 'aktif'],
            ['CUST-IMP', 'Toko Import', 'grosir', '08123', 'cust@import.id', 'Jl. Import 2', 'Bandung', 'Jawa Barat', '40111', '5000000', '14', '1'],
            ['CUST-IMP2', 'Toko Import Dua', 'member', '08124', 'cust2@import.id', 'Jl. Import 3', 'Surabaya', 'Jawa Timur', '60111', '1000000', '7', '1'],
        ]);

        Livewire::actingAs($this->admin)
            ->test(MasterDataImportPanel::class)
            ->set('type', 'customers')
            ->set('file', $file)
            ->call('import')
            ->assertHasNoErrors()
            ->assertSet('result', function (array $result): bool {
                return $result['errors'] === [] && $result['imported'] === 2;
            });

        $this->assertDatabaseHas('customers', [
            'code' => 'CUST-IMP',
            'name' => 'Toko Import',
            'type' => 'reseller',
            'payment_terms_days' => 14,
        ]);

        // 'member' tetap dipertahankan; 'grosir' dipetakan ke 'reseller'.
        $this->assertDatabaseHas('customers', [
            'code' => 'CUST-IMP2',
            'type' => 'member',
        ]);
    }

    public function test_unsupported_file_type_is_rejected(): void
    {
        $file = UploadedFile::fake()->create('data.pdf', 100, 'application/pdf');

        Livewire::actingAs($this->admin)
            ->test(MasterDataImportPanel::class)
            ->set('type', 'products')
            ->set('file', $file)
            ->call('import')
            ->assertHasErrors(['file']);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_template_download_routes_require_permission(): void
    {
        $this->actingAs($this->admin)
            ->get(route('master-data.import-template.products'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($this->admin)
            ->get(route('master-data.import-template.suppliers'))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('master-data.import-template.customers'))
            ->assertOk();

        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get(route('master-data.import-template.products'))
            ->assertForbidden();
    }

    public function test_download_template_button_redirects_to_route(): void
    {
        Livewire::actingAs($this->admin)
            ->test(MasterDataImportPanel::class)
            ->set('type', 'suppliers')
            ->call('downloadTemplate')
            ->assertRedirect(route('master-data.import-template.suppliers'));
    }
}
