<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\PurchaseReturn;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseReturnController extends Controller
{
    public function __construct(
        private readonly GoodsReceipt $goodsReceiptModel,
        private readonly Stock $stockModel,
        private readonly StockMovement $stockMovementModel,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = PurchaseReturn::with(['supplier', 'items.product', 'creator']);

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->where('return_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('return_date', '<=', $request->input('date_to'));
        }

        $paginatedResult = $query->orderByDesc('return_date')->paginate($request->integer('per_page', 15));

        return response()->json($paginatedResult);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'goods_receipt_id' => 'nullable|exists:goods_receipts,id|required_without_all:supplier_id,reference',
            'supplier_id' => 'nullable|exists:suppliers,id|required_with:reference',
            'reference' => 'nullable|string|required_with:supplier_id',
            'return_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.reason' => 'nullable|string',
        ]);

        $result = DB::transaction(function () use ($validated): PurchaseReturn {
            $goodsReceipt = null;
            $warehouseId = null;
            $supplierId = $validated['supplier_id'] ?? null;
            $purchaseOrderId = null;

            if (! empty($validated['goods_receipt_id'])) {
                $goodsReceipt = $this->goodsReceiptModel->with(['purchaseOrder'])->findOrFail($validated['goods_receipt_id']);
                $warehouseId = $goodsReceipt->warehouse_id;
                $supplierId = $goodsReceipt->purchaseOrder->supplier_id;
                $purchaseOrderId = $goodsReceipt->purchase_order_id;
            }

            $returnNumber = $this->generateReturnNumber();

            $totalAmount = collect($validated['items'])->sum(function (array $item): float {
                return (float) $item['quantity'] * (float) $item['unit_price'];
            });

            $purchaseReturn = PurchaseReturn::create([
                'return_number' => $returnNumber,
                'purchase_order_id' => $purchaseOrderId,
                'supplier_id' => $supplierId,
                'return_date' => $validated['return_date'],
                'status' => 'draft',
                'total_amount' => $totalAmount,
                'reason' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                $subtotal = (float) $item['quantity'] * (float) $item['unit_price'];

                $purchaseReturn->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $subtotal,
                    'reason' => $item['reason'] ?? null,
                ]);

                if ($warehouseId !== null) {
                    $stock = $this->stockModel
                        ->where('product_id', $item['product_id'])
                        ->where('warehouse_id', $warehouseId)
                        ->lockForUpdate()
                        ->first();

                    if ($stock !== null) {
                        $stock->decrement('quantity', $item['quantity']);
                    }

                    $this->stockMovementModel->create([
                        'product_id' => $item['product_id'],
                        'warehouse_id' => $warehouseId,
                        'type' => 'out',
                        'quantity' => $item['quantity'],
                        'reference_type' => PurchaseReturn::class,
                        'reference_id' => $purchaseReturn->id,
                        'notes' => 'Purchase return: '.$returnNumber,
                        'created_by' => auth()->id(),
                        'movement_date' => now(),
                    ]);
                }
            }

            return $purchaseReturn;
        });

        $result->load(['supplier', 'items.product', 'creator']);

        return response()->json(['data' => $result], 201);
    }

    public function show(PurchaseReturn $purchaseReturn): JsonResponse
    {
        $purchaseReturn->load(['supplier', 'items.product', 'creator']);

        return response()->json(['data' => $purchaseReturn]);
    }

    public function approve(PurchaseReturn $purchaseReturn): JsonResponse
    {
        if ($purchaseReturn->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft purchase returns can be approved.',
            ], 422);
        }

        $hasMovements = $this->stockMovementModel
            ->where('reference_type', PurchaseReturn::class)
            ->where('reference_id', $purchaseReturn->id)
            ->where('type', 'out')
            ->exists();

        if (! $hasMovements && $purchaseReturn->items()->exists()) {
            return response()->json([
                'message' => 'Stock adjustments must be recorded before approval.',
            ], 422);
        }

        $purchaseReturn->update(['status' => 'approved']);
        $purchaseReturn->load(['supplier', 'items.product', 'creator']);

        return response()->json(['data' => $purchaseReturn]);
    }

    public function cancel(PurchaseReturn $purchaseReturn): JsonResponse
    {
        if ($purchaseReturn->status === 'cancelled') {
            return response()->json([
                'message' => 'Purchase return is already cancelled.',
            ], 422);
        }

        DB::transaction(function () use ($purchaseReturn): void {
            $movements = $this->stockMovementModel
                ->where('reference_type', PurchaseReturn::class)
                ->where('reference_id', $purchaseReturn->id)
                ->where('type', 'out')
                ->get();

            foreach ($movements as $movement) {
                $stock = $this->stockModel
                    ->where('product_id', $movement->product_id)
                    ->where('warehouse_id', $movement->warehouse_id)
                    ->lockForUpdate()
                    ->first();

                if ($stock !== null) {
                    $stock->increment('quantity', $movement->quantity);
                }

                $this->stockMovementModel->create([
                    'product_id' => $movement->product_id,
                    'warehouse_id' => $movement->warehouse_id,
                    'type' => 'in',
                    'quantity' => $movement->quantity,
                    'reference_type' => PurchaseReturn::class,
                    'reference_id' => $purchaseReturn->id,
                    'notes' => 'Reversal of purchase return: '.$purchaseReturn->return_number,
                    'created_by' => auth()->id(),
                    'movement_date' => now(),
                ]);
            }

            $purchaseReturn->update(['status' => 'cancelled']);
        });

        $purchaseReturn->load(['supplier', 'items.product', 'creator']);

        return response()->json(['data' => $purchaseReturn]);
    }

    public function destroy(PurchaseReturn $purchaseReturn): JsonResponse
    {
        if (! in_array($purchaseReturn->status, ['draft', 'cancelled'], true)) {
            return response()->json([
                'message' => 'Only draft or cancelled purchase returns can be deleted.',
            ], 422);
        }

        $purchaseReturn->delete();

        return response()->json(['data' => null]);
    }

    private function generateReturnNumber(): string
    {
        $datePrefix = 'PR-'.now()->format('Ymd').'-';

        $latest = PurchaseReturn::where('return_number', 'like', $datePrefix.'%')
            ->orderByDesc('return_number')
            ->value('return_number');

        $sequence = 1;

        if ($latest !== null) {
            $lastSequence = (int) substr($latest, -4);
            $sequence = $lastSequence + 1;
        }

        return $datePrefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
