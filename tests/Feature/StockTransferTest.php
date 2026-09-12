<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockTransferService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Warehouse $source;

    private Warehouse $destination;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->user = User::factory()->create();
        $gudangRole = Role::where('name', 'staff_gudang')->first();
        $this->user->roles()->attach($gudangRole);

        $this->source = Warehouse::factory()->create();
        $this->destination = Warehouse::factory()->create();

        $category = ProductCategory::factory()->create();
        $unit = ProductUnit::factory()->create();

        $this->product = Product::factory()->create([
            'product_category_id' => $category->id,
            'base_unit_id' => $unit->id,
        ]);

        Stock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->source->id,
            'quantity' => 50,
        ]);
    }

    public function test_full_transfer_flow_moves_stock_between_warehouses(): void
    {
        $service = app(StockTransferService::class);

        $transfer = $service->createTransfer(
            [
                'source_warehouse_id' => $this->source->id,
                'destination_warehouse_id' => $this->destination->id,
            ],
            [['product_id' => $this->product->id, 'quantity' => 20]],
            $this->user->id,
        );

        $this->assertSame('draft', $transfer->status);

        // Approve -> stock leaves source
        $transfer = $service->approve($transfer, $this->user->id);

        $this->assertSame('approved', $transfer->status);
        $this->assertEquals(30, (float) Stock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->source->id)->value('quantity'));
        $this->assertEquals(0, (float) Stock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->destination->id)->value('quantity'));

        // Movements logged for transfer_out
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->source->id,
            'type' => 'transfer_out',
            'quantity' => 20,
        ]);

        // Receive -> stock enters destination
        $transfer = $service->receive(
            $transfer,
            [['stock_transfer_item_id' => $transfer->items[0]->id, 'quantity' => 20]],
            $this->user->id,
        );

        $this->assertSame('received', $transfer->status);
        $this->assertEquals(20, (float) Stock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->destination->id)->value('quantity'));

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->destination->id,
            'type' => 'transfer_in',
            'quantity' => 20,
        ]);
    }

    public function test_transfer_fails_when_source_stock_insufficient(): void
    {
        $service = app(StockTransferService::class);

        $transfer = $service->createTransfer(
            [
                'source_warehouse_id' => $this->source->id,
                'destination_warehouse_id' => $this->destination->id,
            ],
            [['product_id' => $this->product->id, 'quantity' => 999]],
            $this->user->id,
        );

        $this->expectException(\RuntimeException::class);
        $service->approve($transfer, $this->user->id);
    }

    public function test_cancelled_transfer_rejects_when_already_approved(): void
    {
        $service = app(StockTransferService::class);

        $transfer = $service->createTransfer(
            [
                'source_warehouse_id' => $this->source->id,
                'destination_warehouse_id' => $this->destination->id,
            ],
            [['product_id' => $this->product->id, 'quantity' => 5]],
            $this->user->id,
        );

        $service->approve($transfer, $this->user->id);

        $this->expectException(\RuntimeException::class);
        $service->cancel($transfer->fresh());
    }

    public function test_draft_transfer_can_be_cancelled(): void
    {
        $service = app(StockTransferService::class);

        $transfer = $service->createTransfer(
            [
                'source_warehouse_id' => $this->source->id,
                'destination_warehouse_id' => $this->destination->id,
            ],
            [['product_id' => $this->product->id, 'quantity' => 5]],
            $this->user->id,
        );

        $transfer = $service->cancel($transfer);

        $this->assertSame('cancelled', $transfer->status);
        // Stock untouched
        $this->assertEquals(50, (float) Stock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->source->id)->value('quantity'));
    }

    public function test_api_transfer_flow_works(): void
    {
        $payload = [
            'source_warehouse_id' => $this->source->id,
            'destination_warehouse_id' => $this->destination->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson('/api/stock-transfers', $payload);
        $response->assertCreated();

        $transferId = $response->json('data.id');

        $this->actingAs($this->user)
            ->postJson("/api/stock-transfers/{$transferId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        // Stock moved out of source via API too
        $this->assertEquals(40, (float) Stock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->source->id)->value('quantity'));
    }
}
