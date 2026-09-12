<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\SalesOrder;
use App\Services\SalesOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesOrderController extends Controller
{
    public function __construct(
        private readonly SalesOrder $salesOrder,
        private readonly SalesOrderService $salesOrderService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->salesOrder->newQuery()
            ->with(['customer', 'warehouse', 'items.product', 'creator']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        if ($request->filled('date_from')) {
            $query->where('order_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('order_date', '<=', $request->input('date_to'));
        }

        $perPage = (int) $request->input('per_page', 15);
        $paginatedResult = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json($paginatedResult);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'order_date' => ['required', 'date'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'gte:0'],
            'items.*.unit_id' => ['nullable', 'exists:product_units,id'],
        ]);

        $result = DB::transaction(function () use ($validated): SalesOrder {
            $soNumber = $this->generateSoNumber();

            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $baseQty = $this->convertItemToBaseUnit($item);
                $discount = $item['discount_amount'] ?? 0;
                $subtotal += ($baseQty * $item['unit_price']) - $discount;
            }

            $totalAmount = $subtotal;

            $salesOrder = $this->salesOrder->create([
                'so_number' => $soNumber,
                'customer_id' => $validated['customer_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'order_date' => $validated['order_date'],
                'delivery_date' => $validated['delivery_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'draft',
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => $totalAmount,
                'paid_amount' => 0,
                'payment_status' => 'unpaid',
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                $baseQty = $this->convertItemToBaseUnit($item);
                $discount = $item['discount_amount'] ?? 0;
                $itemSubtotal = ($baseQty * $item['unit_price']) - $discount;

                $salesOrder->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $baseQty,
                    'unit_price' => $item['unit_price'],
                    'discount_amount' => $discount,
                    'subtotal' => $itemSubtotal,
                ]);
            }

            return $salesOrder->load(['customer', 'warehouse', 'items.product', 'creator']);
        });

        return response()->json(['data' => $result], 201);
    }

    public function show(SalesOrder $salesOrder): JsonResponse
    {
        $salesOrder->load(['customer', 'warehouse', 'items.product', 'creator']);

        return response()->json(['data' => $salesOrder]);
    }

    public function update(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        if ($salesOrder->status !== 'draft') {
            return response()->json(['message' => 'Only draft sales orders can be updated.'], 422);
        }

        $validated = $request->validate([
            'customer_id' => ['sometimes', 'exists:customers,id'],
            'warehouse_id' => ['sometimes', 'exists:warehouses,id'],
            'order_date' => ['sometimes', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'gte:0'],
            'items.*.unit_id' => ['nullable', 'exists:product_units,id'],
        ]);

        $result = DB::transaction(function () use ($validated, $salesOrder): SalesOrder {
            $updateData = collect($validated)->except('items')->toArray();

            if (isset($validated['items'])) {
                $subtotal = 0;
                foreach ($validated['items'] as $item) {
                    $baseQty = $this->convertItemToBaseUnit($item);
                    $discount = $item['discount_amount'] ?? 0;
                    $subtotal += ($baseQty * $item['unit_price']) - $discount;
                }

                $updateData['subtotal'] = $subtotal;
                $updateData['total_amount'] = $subtotal;

                $salesOrder->items()->delete();

                foreach ($validated['items'] as $item) {
                    $baseQty = $this->convertItemToBaseUnit($item);
                    $discount = $item['discount_amount'] ?? 0;
                    $itemSubtotal = ($baseQty * $item['unit_price']) - $discount;

                    $salesOrder->items()->create([
                        'product_id' => $item['product_id'],
                        'quantity' => $baseQty,
                        'unit_price' => $item['unit_price'],
                        'discount_amount' => $discount,
                        'subtotal' => $itemSubtotal,
                    ]);
                }
            }

            $salesOrder->update($updateData);

            return $salesOrder->load(['customer', 'warehouse', 'items.product', 'creator']);
        });

        return response()->json(['data' => $result]);
    }

    public function destroy(SalesOrder $salesOrder): JsonResponse
    {
        if ($salesOrder->status !== 'draft') {
            return response()->json(['message' => 'Only draft sales orders can be deleted.'], 422);
        }

        DB::transaction(function () use ($salesOrder): void {
            $salesOrder->items()->delete();
            $salesOrder->delete();
        });

        return response()->json(['message' => 'Sales order deleted successfully.']);
    }

    public function confirm(SalesOrder $salesOrder): JsonResponse
    {
        try {
            $result = $this->salesOrderService->confirm($salesOrder, (int) auth()->id());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }

    public function cancel(SalesOrder $salesOrder): JsonResponse
    {
        try {
            $result = $this->salesOrderService->cancel($salesOrder, (int) auth()->id());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }

    /**
     * Convert item quantity to base unit if unit_id is provided.
     *
     * @param  array{product_id: int, quantity: float, unit_id?: int}  $item
     */
    private function convertItemToBaseUnit(array $item): float
    {
        $quantity = (float) $item['quantity'];

        if (empty($item['unit_id'])) {
            return $quantity;
        }

        $unit = ProductUnit::findOrFail($item['unit_id']);

        if ($unit->is_base) {
            return $quantity;
        }

        $product = Product::findOrFail($item['product_id']);

        return $product->convertToBaseUnit($quantity, $unit);
    }

    private function generateSoNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "SO-{$date}-";

        $lastOrder = $this->salesOrder->withTrashed()
            ->where('so_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->first();

        if ($lastOrder && $lastOrder->so_number) {
            $lastSequence = (int) substr($lastOrder->so_number, -4);
            $sequence = $lastSequence + 1;
        } else {
            $sequence = 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
