<?php

namespace Tests\Feature;

use App\Models\AccountPayable;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountPayableApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();

        $permission = Permission::create(['name' => 'manage-finance']);
        $role = Role::create(['name' => 'finance']);
        $role->permissions()->attach($permission);
        $this->user->roles()->attach($role);

        Sanctum::actingAs($this->user);
    }

    public function test_can_list_account_payables(): void
    {
        AccountPayable::factory()->count(3)->create();

        $response = $this->getJson('/api/account-payables');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_account_payable(): void
    {
        $supplier = Supplier::factory()->create();

        $payload = [
            'ap_number' => 'AP-TEST-001',
            'supplier_id' => $supplier->id,
            'total_amount' => 5000000,
            'due_date' => now()->addMonth()->toDateString(),
            'notes' => 'Test AP',
        ];

        $response = $this->postJson('/api/account-payables', $payload);

        $response->assertCreated()
            ->assertJsonFragment([
                'ap_number' => 'AP-TEST-001',
                'status' => 'open',
            ]);

        $this->assertDatabaseHas('account_payables', [
            'ap_number' => 'AP-TEST-001',
            'remaining_amount' => 5000000,
            'paid_amount' => 0,
        ]);
    }

    public function test_can_update_paid_amount_and_status_auto_changes(): void
    {
        $ap = AccountPayable::factory()->open()->create(['total_amount' => 1000000, 'remaining_amount' => 1000000]);

        $response = $this->postJson("/api/account-payables/{$ap->id}/pay", [
            'amount' => 500000,
        ]);

        $response->assertOk()
            ->assertJsonFragment(['status' => 'partial']);

        $this->assertEquals(500000, (float) $ap->fresh()->remaining_amount);

        // Cash-out otomatis tercatat
        $this->assertDatabaseHas('cash_transactions', [
            'type' => 'out',
            'amount' => 500000,
        ]);
    }

    public function test_full_payment_sets_status_to_paid(): void
    {
        $ap = AccountPayable::factory()->open()->create(['total_amount' => 1000000, 'remaining_amount' => 1000000]);

        $response = $this->postJson("/api/account-payables/{$ap->id}/pay", [
            'amount' => 1000000,
        ]);

        $response->assertOk()
            ->assertJsonFragment(['status' => 'paid']);

        $this->assertEquals(0, (float) $ap->fresh()->remaining_amount);
    }

    public function test_can_delete_account_payable(): void
    {
        $ap = AccountPayable::factory()->create();

        $response = $this->deleteJson("/api/account-payables/{$ap->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('account_payables', ['id' => $ap->id]);
    }
}
