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
use Livewire\Livewire;
use Tests\TestCase;

class ProductBarcodeLabelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $superAdmin = Role::where('name', 'super_admin')->first();
        $this->admin->roles()->attach($superAdmin);

        $category = ProductCategory::factory()->create();
        $unit = ProductUnit::factory()->create();

        $this->product = Product::factory()->create([
            'product_category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'barcode' => '1234567890123',
        ]);
    }

    public function test_barcode_label_pdf_can_be_generated(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('pdf.product-label', ['id' => $this->product->id]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_barcode_label_pdf_supports_multiple_copies(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('pdf.product-label', ['id' => $this->product->id, 'copies' => 3]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_label_route_requires_manage_products_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('pdf.product-label', ['id' => $this->product->id]));

        $response->assertForbidden();
    }

    public function test_print_label_redirects_to_pdf_route(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ProductList::class)
            ->call('printLabel', $this->product->id)
            ->assertRedirect(route('pdf.product-label', ['id' => $this->product->id, 'copies' => 1]));
    }

    public function test_print_label_requires_barcode(): void
    {
        $noBarcode = Product::factory()->create([
            'product_category_id' => $this->product->product_category_id,
            'base_unit_id' => $this->product->base_unit_id,
            'barcode' => null,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ProductList::class)
            ->call('printLabel', $noBarcode->id)
            ->assertNoRedirect();
    }
}
