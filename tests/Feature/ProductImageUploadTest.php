<?php

namespace Tests\Feature;

use App\Livewire\ProductList;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProductImageUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ProductCategory $category;

    private ProductUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $superAdmin = Role::where('name', 'super_admin')->first();
        $this->admin->roles()->attach($superAdmin);

        $this->category = ProductCategory::factory()->create();
        $this->unit = ProductUnit::factory()->create();
    }

    /**
     * Build a fake image upload without depending on the GD extension.
     */
    private function fakeImage(string $name = 'produk.jpg'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 100, 'image/jpeg');
    }

    public function test_product_image_can_be_uploaded_on_create(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->admin)
            ->test(ProductList::class)
            ->call('openCreate')
            ->set('sku', 'IMG-001')
            ->set('name', 'Produk Bergambar')
            ->set('product_category_id', (string) $this->category->id)
            ->set('base_unit_id', (string) $this->unit->id)
            ->set('purchase_price', '1000')
            ->set('selling_price', '2000')
            ->set('image', $this->fakeImage())
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::where('sku', 'IMG-001')->firstOrFail();

        $this->assertNotNull($product->image_path);
        Storage::disk('public')->assertExists($product->image_path);
    }

    public function test_product_image_is_replaced_on_update(): void
    {
        Storage::fake('public');

        $old = $this->fakeImage('lama.jpg')->store('products', 'public');

        $product = Product::factory()->create([
            'product_category_id' => $this->category->id,
            'base_unit_id' => $this->unit->id,
            'image_path' => $old,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ProductList::class)
            ->call('openEdit', $product->id)
            ->set('image', $this->fakeImage('baru.jpg'))
            ->call('save')
            ->assertHasNoErrors();

        $product->refresh();

        $this->assertNotSame($old, $product->image_path);
        Storage::disk('public')->assertExists($product->image_path);
        Storage::disk('public')->assertMissing($old);
    }

    public function test_product_image_can_be_removed(): void
    {
        Storage::fake('public');

        $path = $this->fakeImage('hapus.jpg')->store('products', 'public');

        $product = Product::factory()->create([
            'product_category_id' => $this->category->id,
            'base_unit_id' => $this->unit->id,
            'image_path' => $path,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ProductList::class)
            ->call('openEdit', $product->id)
            ->call('removeImage')
            ->assertHasNoErrors();

        $product->refresh();

        $this->assertNull($product->image_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_invalid_file_type_is_rejected(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->admin)
            ->test(ProductList::class)
            ->call('openCreate')
            ->set('sku', 'IMG-002')
            ->set('name', 'Produk Invalid')
            ->set('product_category_id', (string) $this->category->id)
            ->set('base_unit_id', (string) $this->unit->id)
            ->set('purchase_price', '1000')
            ->set('selling_price', '2000')
            ->set('image', UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'))
            ->call('save')
            ->assertHasErrors(['image']);

        $this->assertDatabaseMissing('products', ['sku' => 'IMG-002']);
    }
}
