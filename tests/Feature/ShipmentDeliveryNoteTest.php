<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Livewire\ShipmentList;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShipmentDeliveryNoteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $salesRole = Role::where('name', 'sales_marketing')->firstOrFail();
        $this->admin->roles()->attach($salesRole);

        $warehouse = Warehouse::factory()->create();
        $customer = Customer::factory()->create();
        $category = ProductCategory::factory()->create();
        $unit = ProductUnit::factory()->create();

        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'base_unit_id' => $unit->id,
        ]);

        $so = SalesOrder::factory()->create([
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'processing',
        ]);

        SalesOrderItem::factory()->create([
            'sales_order_id' => $so->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 50000,
            'subtotal' => 100000,
        ]);

        $courier = Courier::create([
            'code' => 'JNE',
            'name' => 'JNE Express',
            'type' => 'external',
            'cost_per_kg' => 10000,
        ]);

        $this->shipment = Shipment::create([
            'shipment_number' => 'SJ-20260916-0001',
            'sales_order_id' => $so->id,
            'courier_id' => $courier->id,
            'recipient_name' => 'Budi Santoso',
            'recipient_phone' => '08123456789',
            'destination_address' => 'Jl. Merdeka No. 1, Jakarta',
            'total_weight_kg' => 3,
            'shipping_cost' => 30000,
            'status' => ShipmentStatus::Preparing,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_delivery_note_pdf_can_be_generated(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('pdf.delivery-note', ['id' => $this->shipment->id]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_delivery_note_route_requires_manage_logistics_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('pdf.delivery-note', ['id' => $this->shipment->id]));

        $response->assertForbidden();
    }

    public function test_print_delivery_note_redirects_to_pdf_route(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ShipmentList::class)
            ->call('printDeliveryNote', $this->shipment->id)
            ->assertRedirect(route('pdf.delivery-note', ['id' => $this->shipment->id]));
    }
}
