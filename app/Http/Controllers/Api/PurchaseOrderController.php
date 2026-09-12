<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PurchaseOrderController extends Controller
{
    public function __construct(private PurchaseOrderService $poService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PurchaseOrder::with(['supplier', 'warehouse', 'items']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        $items = $query->orderByDesc('order_date')->paginate($request->integer('per_page', 15));

        return new AnonymousResourceCollection(
            $items,
            PurchaseOrderResource::class
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'order_date' => 'nullable|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'tax_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.unit_id' => 'nullable|exists:product_units,id',
        ]);

        $po = $this->poService->createPurchaseOrder(
            $validated,
            $validated['items'],
            auth()->id(),
        );

        return response()->json(['data' => $po], 201);
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder->load(['supplier', 'warehouse', 'items.product', 'goodsReceipts.items', 'creator', 'approver']);

        return response()->json(['data' => $purchaseOrder]);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        if (! in_array($purchaseOrder->status, ['draft', 'submitted'])) {
            return response()->json(['message' => 'Cannot edit PO with status: '.$purchaseOrder->status], 422);
        }

        $validated = $request->validate([
            'expected_delivery_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $purchaseOrder->update($validated);

        return response()->json(['data' => $purchaseOrder->fresh()]);
    }

    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        if ($purchaseOrder->status !== 'draft') {
            return response()->json(['message' => 'Only draft PO can be deleted.'], 422);
        }

        $purchaseOrder->delete();

        return response()->json(null, 204);
    }

    public function approve(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $po = $this->poService->approve($purchaseOrder, auth()->id());

        return response()->json(['data' => $po]);
    }

    public function submitToSupplier(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $po = $this->poService->submitToSupplier($purchaseOrder);

        return response()->json(['data' => $po]);
    }
}
