<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase3PageRenderingTest extends TestCase
{
    use RefreshDatabase;

    private User $finance;

    private User $sales;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->finance = User::factory()->create();
        $this->finance->roles()->attach(Role::where('name', 'staff_keuangan')->first());

        $this->sales = User::factory()->create();
        $this->sales->roles()->attach(Role::where('name', 'sales_marketing')->first());
    }

    public function test_shipments_page_renders_inside_erp_layout_with_sidebar(): void
    {
        Warehouse::factory()->create();

        $response = $this->actingAs($this->sales)->get('/shipments');

        $response->assertOk();
        // Sidebar layout ERP hadir
        $response->assertSee('Surat Jalan', false);
        // Menu sidebar tetap dirender (bukan halaman kosong tanpa layout)
        $response->assertSee('Dashboard', false);
        $response->assertSee('ERP', false);
    }

    public function test_bank_reconciliations_page_renders_inside_erp_layout_with_sidebar(): void
    {
        $response = $this->actingAs($this->finance)->get('/bank-reconciliations');

        $response->assertOk();
        $response->assertSee('Rekonsiliasi Bank', false);
        $response->assertSee('Dashboard', false);
    }

    public function test_shipments_page_denied_without_manage_logistics(): void
    {
        $kasir = User::factory()->create();
        $kasir->roles()->attach(Role::where('name', 'kasir')->first());

        $response = $this->actingAs($kasir)->get('/shipments');
        $response->assertForbidden();
    }

    public function test_bank_reconciliations_page_denied_without_manage_finance(): void
    {
        $sales = User::factory()->create();
        $sales->roles()->attach(Role::where('name', 'sales_marketing')->first());

        $response = $this->actingAs($sales)->get('/bank-reconciliations');
        $response->assertForbidden();
    }
}
