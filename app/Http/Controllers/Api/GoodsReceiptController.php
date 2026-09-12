<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoodsReceiptController extends Controller
{
    public function __construct(private PurchaseOrderService $poService) {}

    public function index(Request $request): JsonResponse
    {
        $query = GoodsReceipt::with(['purchaseOrder.supplier', 'warehouse', 'items.product', 'receiver']);

        if ($request->filled('purchase_order_id')) {
            $query->where('purchase_order_id', $request->input('purchase_order_id'));
        }

        $items = $query->orderByDesc('receipt_date')->paginate($request->integer('per_page', 15));

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.purchase_order_item_id' => 'required|exists:purchase_order_items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_id' => 'nullable|exists:product_units,id',
            'items.*.batch_number' => 'nullable|string',
            'items.*.expiry_date' => 'nullable|date',
        ]);

        $po = PurchaseOrder::findOrFail($validated['purchase_order_id']);

        $gr = $this->poService->receiveGoods(
            $po,
            $validated['items'],
            auth()->id(),
            $validated['notes'] ?? null,
        );

        return response()->json(['data' => $gr->load('items.product')], 201);
    }

    public function show(GoodsReceipt $goodsReceipt): JsonResponse
    {
        $goodsReceipt->load(['purchaseOrder.supplier', 'warehouse', 'items.product', 'receiver']);

        return response()->json(['data' => $goodsReceipt]);
    }
}
