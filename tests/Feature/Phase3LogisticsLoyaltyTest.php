<?php

namespace Tests\Feature;

use App\Enums\LoyaltyTier;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\CustomerLoyaltyProfile;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\LoyaltyService;
use App\Services\ShipmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase3LogisticsLoyaltyTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_sale_awards_loyalty_points_to_member(): void
    {
        $member = Customer::factory()->create(['type' => 'member']);
        $general = Customer::factory()->create(['type' => 'general']);

        $service = app(LoyaltyService::class);

        // Rp 250.000 -> 25 poin
        $balance = $service->awardForSale($member->id, 250000, 'sales_transaction', 1, null);
        $this->assertSame(25, $balance);

        // Non-member: tidak dapat poin
        $this->assertSame(0, $service->awardForSale($general->id, 250000, 'sales_transaction', 2, null));

        $profile = CustomerLoyaltyProfile::where('customer_id', $member->id)->first();
        $this->assertSame(25, (int) $profile->points_balance);
        $this->assertSame(25, (int) $profile->lifetime_points);
        $this->assertTrue($profile->tier === LoyaltyTier::Bronze);
    }

    public function test_tier_upgrades_at_thresholds(): void
    {
        $member = Customer::factory()->create(['type' => 'member']);
        $service = app(LoyaltyService::class);

        // 1000 poin -> Silver
        $service->adjust($member->id, 'earn', 1000, 'test', null);
        $profile = CustomerLoyaltyProfile::where('customer_id', $member->id)->first();
        $this->assertSame('silver', $profile->fresh()->tier->value);

        // sampai 5000 -> Gold
        $service->adjust($member->id, 'earn', 4000, 'test', null);
        $this->assertSame('gold', $profile->fresh()->tier->value);
        $this->assertSame(5.0, $profile->fresh()->tier->discountPercent());
    }

    public function test_redeem_rejects_insufficient_points(): void
    {
        $member = Customer::factory()->create(['type' => 'member']);
        $service = app(LoyaltyService::class);

        $service->adjust($member->id, 'earn', 50, 'test', null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Poin tidak mencukupi.');
        $service->redeem($member->id, 51, 'manual');
    }

    public function test_redeem_reduces_balance(): void
    {
        $member = Customer::factory()->create(['type' => 'member']);
        $service = app(LoyaltyService::class);

        $service->adjust($member->id, 'earn', 100, 'test', null);
        $balance = $service->redeem($member->id, 30, 'manual', null, null, 'hadiah');

        $this->assertSame(70, $balance);
        $this->assertDatabaseHas('loyalty_points', [
            'customer_id' => $member->id,
            'type' => 'redeem',
            'points' => -30,
            'balance_after' => 70,
        ]);
        // lifetime tidak berkurang saat redeem
        $this->assertSame(100,
            (int) CustomerLoyaltyProfile::where('customer_id', $member->id)->value('lifetime_points'));
    }

    public function test_shipment_flow_from_so_to_delivered(): void
    {
        [$so, $courier] = $this->makeDeliverableSo();
        $user = User::factory()->create();
        $service = app(ShipmentService::class);

        $shipment = $service->createFromSalesOrder($so, ['courier_id' => $courier->id, 'total_weight_kg' => 5], $user->id);
        $this->assertSame('preparing', $shipment->status->value);
        $this->assertSame('SJ-', substr($shipment->shipment_number, 0, 3));
        // Ongkir otomatis = cost_per_kg × berat
        $this->assertEquals(5 * 10000, (float) $shipment->shipping_cost);
        // SO berpindah ke processing
        $this->assertSame('processing', $so->fresh()->status);

        // Duplicate ditolak
        $this->expectException(\RuntimeException::class);
        $service->createFromSalesOrder($so, [], $user->id);
    }

    public function test_shipment_status_transitions_and_so_completion(): void
    {
        [$so, $courier] = $this->makeDeliverableSo();
        $user = User::factory()->create();
        $service = app(ShipmentService::class);

        $shipment = $service->createFromSalesOrder($so, ['courier_id' => $courier->id], $user->id);

        // preparing -> dispatched -> in_transit -> delivered
        $shipment = $service->dispatch($shipment, $user->id);
        $this->assertSame('dispatched', $shipment->status->value);

        $shipment = $service->markInTransit($shipment, $user->id);
        $this->assertSame('in_transit', $shipment->status->value);

        $shipment = $service->markDelivered($shipment, $user->id);
        $this->assertSame('delivered', $shipment->status->value);
        $this->assertNotNull($shipment->delivered_at);
        // SO ikut selesai
        $this->assertSame('delivered', $so->fresh()->status);
    }

    public function test_shipment_cannot_be_cancelled_after_dispatch(): void
    {
        [$so, $courier] = $this->makeDeliverableSo();
        $user = User::factory()->create();
        $service = app(ShipmentService::class);

        $shipment = $service->createFromSalesOrder($so, [], $user->id);
        $shipment = $service->dispatch($shipment, $user->id);

        $this->expectException(\RuntimeException::class);
        $service->cancel($shipment);
    }

    public function test_shipment_rejects_draft_so(): void
    {
        $so = SalesOrder::factory()->create(['status' => 'draft']);
        $user = User::factory()->create();
        $service = app(ShipmentService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tidak dapat dikirim');
        $service->createFromSalesOrder($so, [], $user->id);
    }

    /**
     * @return array{0: SalesOrder, 1: Courier}
     */
    private function makeDeliverableSo(): array
    {
        $so = SalesOrder::factory()->create(['status' => 'confirmed']);
        $courier = Courier::factory()->create(['cost_per_kg' => 10000]);

        return [$so, $courier];
    }
}
