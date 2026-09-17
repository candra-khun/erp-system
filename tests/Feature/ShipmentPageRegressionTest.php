<?php

namespace Tests\Feature;

use App\Livewire\ShipmentList;
use App\Models\Courier;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShipmentPageRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $sales;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->sales = User::factory()->create();
        $this->sales->roles()->attach(Role::where('name', 'sales_marketing')->first());
    }

    public function test_shipment_form_with_courier_dropdown_renders_without_error(): void
    {
        Courier::factory()->count(3)->create();

        // Skenario bug asli: klik "+ Surat Jalan" memicu re-render form
        // (dulu 500: Class "Courier" not found di blade)
        Livewire::test(ShipmentList::class)
            ->call('openForm')
            ->assertHasNoErrors()
            ->assertSet('showForm', true)
            ->assertSee('Pilih SO')
            ->assertSee('Tanpa kurir');

        // Full page render juga tetap sehat
        $response = $this->actingAs($this->sales)->get('/shipments');
        $response->assertOk();
        $response->assertSee('Kurir', false);
    }

    public function test_courier_save_and_reset_does_not_throw(): void
    {
        // Simulasi aksi Livewire: openForm -> saveCourier (via HTTP test biasa
        // tidak bisa; gunakan component test langsung)
        $component = Livewire::test(ShipmentList::class)
            ->set('courierCode', 'KUR-T1')
            ->set('courierName', 'Kurir Tes')
            ->set('courierType', 'external')
            ->set('courierCostPerKg', 9000)
            ->call('saveCourier');

        $component->assertHasNoErrors();
        $this->assertDatabaseHas('couriers', [
            'code' => 'KUR-T1',
            'name' => 'Kurir Tes',
            'is_active' => true,
        ]);
        $component->assertSet('courierActive', true);
    }

    public function test_edit_courier_then_save_keeps_state_consistent(): void
    {
        $courier = Courier::factory()->create();

        Livewire::test(ShipmentList::class)
            ->call('editCourier', $courier->id)
            ->set('courierName', 'Nama Baru')
            ->call('saveCourier')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('couriers', [
            'id' => $courier->id,
            'name' => 'Nama Baru',
        ]);
    }

    public function test_erp_layout_loads_livewire_scripts_only_once(): void
    {
        $response = $this->actingAs($this->sales)->get('/shipments');
        $html = $response->getContent();

        // Livewire inject script sekali; TIDAK boleh ada app.js Vite (Alpine ganda)
        $this->assertStringContainsString('livewire.js', $html);
        $this->assertSame(
            1,
            substr_count($html, 'livewire.js'),
            'livewire.js harus dimuat tepat sekali'
        );
        $this->assertSame(
            0,
            preg_match('#<script[^>]+assets/app-[^>]+\.js#', $html),
            'app.js (Alpine ganda) tidak boleh ikut dimuat di layout ERP'
        );
    }
}
