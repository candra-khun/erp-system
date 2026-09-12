<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesTransaction;
use App\Models\SalesTransactionItem;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SalesTransactionController extends Controller
{
    public function __construct(
        private readonly SalesTransaction $salesTransaction,
        private readonly SalesTransactionItem $salesTransactionItem,
        private readonly Stock $stock,
        private readonly StockMovement $stockMovement,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->salesTransaction->query()
            ->with(['warehouse', 'customer', 'cashier', 'posShift', 'items.product']);

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        if ($request->filled('cashier_id')) {
            $query->where('cashier_id', $request->input('cashier_id'));
        }

        if ($request->filled('pos_shift_id')) {
            $query->where('pos_shift_id', $request->input('pos_shift_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $transactions = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json($transactions);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'pos_shift_id' => ['nullable', 'exists:pos_shifts,id'],
            'payment_method' => ['required', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'gte:0'],
            'paid_amount' => ['required', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $transaction = DB::transaction(function () use ($validated) {
            $subtotal = 0;
            $totalDiscount = 0;

            foreach ($validated['items'] as $item) {
                $itemSubtotal = $item['quantity'] * $item['unit_price'];
                $itemDiscount = $item['discount_amount'] ?? 0;
                $subtotal += $itemSubtotal;
                $totalDiscount += $itemDiscount;
            }

            $taxRate = 0.11;
            $taxAmount = ($subtotal - $totalDiscount) * $taxRate;
            $totalAmount = $subtotal - $totalDiscount + $taxAmount;
            $changeAmount = max(0, $validated['paid_amount'] - $totalAmount);

            $transactionNumber = $this->generateTransactionNumber();

            $transaction = $this->salesTransaction->create([
                'transaction_number' => $transactionNumber,
                'warehouse_id' => $validated['warehouse_id'],
                'customer_id' => $validated['customer_id'] ?? null,
                'pos_shift_id' => $validated['pos_shift_id'] ?? null,
                'cashier_id' => auth()->id(),
                'payment_method' => $validated['payment_method'],
                'subtotal' => $subtotal,
                'discount_amount' => $totalDiscount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'paid_amount' => $validated['paid_amount'],
                'change_amount' => $changeAmount,
                'status' => 'completed',
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $itemSubtotal = ($item['quantity'] * $item['unit_price']) - ($item['discount_amount'] ?? 0);

                $this->salesTransactionItem->create([
                    'sales_transaction_id' => $transaction->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'subtotal' => $itemSubtotal,
                ]);

                $this->deductStock(
                    productId: $item['product_id'],
                    warehouseId: $validated['warehouse_id'],
                    quantity: $item['quantity'],
                    referenceType: SalesTransaction::class,
                    referenceId: $transaction->id,
                );
            }

            return $transaction;
        });

        $transaction->load(['warehouse', 'customer', 'cashier', 'posShift', 'items.product']);

        return response()->json(['data' => $transaction], 201);
    }

    public function show(SalesTransaction $salesTransaction): JsonResponse
    {
        $salesTransaction->load(['warehouse', 'customer', 'cashier', 'posShift', 'items.product']);

        return response()->json(['data' => $salesTransaction]);
    }

    public function void(SalesTransaction $salesTransaction): JsonResponse
    {
        if ($salesTransaction->status === 'voided') {
            return response()->json(['message' => 'Transaction is already voided.'], 422);
        }

        if ($salesTransaction->status !== 'completed') {
            return response()->json(['message' => 'Only completed transactions can be voided.'], 422);
        }

        DB::transaction(function () use ($salesTransaction) {
            foreach ($salesTransaction->items as $item) {
                $this->restoreStock(
                    productId: $item->product_id,
                    warehouseId: $salesTransaction->warehouse_id,
                    quantity: $item->quantity,
                    referenceType: SalesTransaction::class,
                    referenceId: $salesTransaction->id,
                    notes: 'Void transaction '.$salesTransaction->transaction_number,
                );
            }

            $salesTransaction->update(['status' => 'voided']);
        });

        $salesTransaction->load(['warehouse', 'customer', 'cashier', 'posShift', 'items.product']);

        return response()->json(['data' => $salesTransaction]);
    }

    private function generateTransactionNumber(): string
    {
        $date = Carbon::now()->format('Ymd');
        $prefix = 'POS-'.$date.'-';

        $lastTransaction = $this->salesTransaction
            ->where('transaction_number', 'like', $prefix.'%')
            ->orderByDesc('transaction_number')
            ->first();

        $sequence = 1;

        if ($lastTransaction) {
            $lastSequence = (int) substr($lastTransaction->transaction_number, -4);
            $sequence = $lastSequence + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function deductStock(int $productId, int $warehouseId, float $quantity, string $referenceType, int $referenceId): void
    {
        $stock = $this->stock
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if (! $stock || $stock->quantity < $quantity) {
            throw new \RuntimeException('Insufficient stock for product ID: '.$productId);
        }

        $stock->decrement('quantity', $quantity);

        $this->stockMovement->create([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'type' => 'out',
            'quantity' => $quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => 'POS Sale',
            'created_by' => auth()->id(),
            'movement_date' => Carbon::now(),
        ]);
    }

    private function restoreStock(int $productId, int $warehouseId, float $quantity, string $referenceType, int $referenceId, string $notes = ''): void
    {
        $stock = $this->stock
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if (! $stock) {
            $this->stock->create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'quantity' => $quantity,
            ]);
        } else {
            $stock->increment('quantity', $quantity);
        }

        $this->stockMovement->create([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'type' => 'in',
            'quantity' => $quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes ?: 'Stock restored',
            'created_by' => auth()->id(),
            'movement_date' => Carbon::now(),
        ]);
    }
}
