<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ScopesWarehouseAccess;
use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
use App\Models\Shipment;
use App\Services\ShipmentService;
use App\Support\WarehouseAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ShipmentController extends Controller
{
    use ScopesWarehouseAccess;

    public function __construct(private readonly ShipmentService $shipmentService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Shipment::with(['salesOrder.customer', 'courier'])
            ->accessibleWarehouse($this->accessibleWarehouseIds())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('search'), function ($q) use ($request): void {
                $search = (string) $request->input('search');
                $q->where('shipment_number', 'like', "%{$search}%")
                    ->orWhereHas('salesOrder', fn ($sq) => $sq->where('so_number', 'like', "%{$search}%"));
            })
            ->orderByDesc('id');

        return response()->json(['data' => $query->paginate($request->integer('per_page', 15))]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sales_order_id' => 'required|exists:sales_orders,id',
            'courier_id' => 'nullable|exists:couriers,id',
            'recipient_name' => 'nullable|string|max:150',
            'recipient_phone' => 'nullable|string|max:30',
            'destination_address' => 'nullable|string|max:500',
            'total_weight_kg' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $salesOrder = SalesOrder::findOrFail((int) $validated['sales_order_id']);

            $warehouseId = $salesOrder->warehouse_id;
            if ($warehouseId !== null && ! WarehouseAccess::canAccess($this->user(), (int) $warehouseId)) {
                abort(403, 'Anda tidak memiliki akses ke sales order dari cabang ini.');
            }

            $shipment = $this->shipmentService->createFromSalesOrder(
                $salesOrder,
                $validated,
                (int) $request->user()?->id,
            );
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $shipment], 201);
    }

    /**
     * Resolve a shipment the current user may access (PRD §2 scoping cabang).
     *
     * @throws HttpException 403 bila shipment milik cabang lain.
     */
    private function resolveAccessibleShipment(Shipment $shipment): Shipment
    {
        $warehouseId = $shipment->load('salesOrder')->salesOrder?->warehouse_id;

        if ($warehouseId !== null && ! WarehouseAccess::canAccess($this->user(), (int) $warehouseId)) {
            abort(403, 'Anda tidak memiliki akses ke pengiriman dari cabang ini.');
        }

        return $shipment;
    }

    public function show(Shipment $shipment): JsonResponse
    {
        return response()->json(['data' => $this->resolveAccessibleShipment($shipment)->load(['salesOrder.items.product', 'salesOrder.customer', 'courier'])]);
    }

    public function dispatch(Shipment $shipment): JsonResponse
    {
        try {
            $shipment = $this->shipmentService->dispatch($this->resolveAccessibleShipment($shipment), (int) auth()->id());
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $shipment]);
    }

    public function markInTransit(Shipment $shipment): JsonResponse
    {
        try {
            $shipment = $this->shipmentService->markInTransit($this->resolveAccessibleShipment($shipment), (int) auth()->id());
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $shipment]);
    }

    public function markDelivered(Shipment $shipment): JsonResponse
    {
        try {
            $shipment = $this->shipmentService->markDelivered($this->resolveAccessibleShipment($shipment), (int) auth()->id());
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $shipment]);
    }

    public function cancel(Shipment $shipment): JsonResponse
    {
        try {
            $shipment = $this->shipmentService->cancel($this->resolveAccessibleShipment($shipment));
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $shipment]);
    }
}
