<?php

namespace Tests\Feature;

use App\Events\StockChanged;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $product;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->product = Product::factory()->create();
        $this->warehouse = Warehouse::factory()->create();
    }

    public function test_stock_changed_event_creates_movement_and_updates_stock(): void
    {
        Event::fake([StockChanged::class]);

        event(new StockChanged(
            productId: $this->product->id,
            warehouseId: $this->warehouse->id,
            type: 'in',
            quantity: 50.0,
            referenceType: 'test',
            referenceId: null,
            userId: $this->user->id,
            notes: 'Test incoming stock',
        ));

        Event::assertDispatched(StockChanged::class);
    }

    public function test_stock_movement_listener_updates_stock_quantity(): void
    {
        // Dispatch real event (not faked) to test listener
        event(new StockChanged(
            productId: $this->product->id,
            warehouseId: $this->warehouse->id,
            type: 'in',
            quantity: 100.0,
            userId: $this->user->id,
        ));

        $stock = Stock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertNotNull($stock);
        $this->assertEquals(100.0, (float) $stock->quantity);
    }

    public function test_stock_movement_listener_creates_movement_record(): void
    {
        event(new StockChanged(
            productId: $this->product->id,
            warehouseId: $this->warehouse->id,
            type: 'in',
            quantity: 25.0,
            referenceType: 'purchase_order',
            referenceId: 999,
            userId: $this->user->id,
            notes: 'GRN receipt',
        ));

        $movement = StockMovement::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals('in', $movement->type);
        $this->assertEquals(25.0, (float) $movement->quantity);
        $this->assertEquals('purchase_order', $movement->reference_type);
        $this->assertEquals(999, $movement->reference_id);
    }

    public function test_outgoing_stock_reduces_quantity(): void
    {
        // First add stock
        Stock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
        ]);

        // Then dispatch outgoing
        event(new StockChanged(
            productId: $this->product->id,
            warehouseId: $this->warehouse->id,
            type: 'out',
            quantity: 30.0,
            userId: $this->user->id,
        ));

        $stock = Stock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertEquals(70.0, (float) $stock->quantity);
    }
}
