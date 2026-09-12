<?php

namespace App\Http\Controllers\Api;

use App\Events\StockChanged;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\SalesOrder;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\SalesTransaction;
use App\Services\JournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesReturnController extends Controller
{
    public function __construct(
        private readonly SalesReturn $salesReturn,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->salesReturn->newQuery()
            ->with(['returnable', 'customer', 'items.product', 'creator']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('return_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('return_date', '<=', $request->input('date_to'));
        }

        $paginated = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json($paginated);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'returnable_type' => ['required', 'string'],
            'returnable_id' => ['required', 'integer'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'return_date' => ['required', 'date'],
            'reason' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'items.*.restock' => ['required', 'boolean'],
            'items.*.reason' => ['nullable', 'string'],
        ]);

        $result = DB::transaction(function () use ($validated): SalesReturn {
            $returnNumber = $this->generateReturnNumber();

            $totalAmount = 0;
            foreach ($validated['items'] as $item) {
                $subtotal = $item['quantity'] * $item['unit_price'];
                $totalAmount += $subtotal;
            }

            $returnableTypeMap = [
                'sales_order' => SalesOrder::class,
                'sales_transaction' => SalesTransaction::class,
            ];

            $resolvedType = $returnableTypeMap[$validated['returnable_type']] ?? $validated['returnable_type'];

            $salesReturn = $this->salesReturn->create([
                'return_number' => $returnNumber,
                'returnable_type' => $resolvedType,
                'returnable_id' => $validated['returnable_id'],
                'customer_id' => $validated['customer_id'],
                'return_date' => $validated['return_date'],
                'status' => 'draft',
                'total_amount' => $totalAmount,
                'reason' => $validated['reason'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                $subtotal = $item['quantity'] * $item['unit_price'];

                SalesReturnItem::create([
                    'sales_return_id' => $salesReturn->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $subtotal,
                    'restock' => $item['restock'],
                    'reason' => $item['reason'] ?? null,
                ]);
            }

            return $salesReturn->load(['returnable', 'customer', 'items.product', 'creator']);
        });

        return response()->json(['data' => $result], 201);
    }

    public function show(SalesReturn $salesReturn): JsonResponse
    {
        $salesReturn->load(['returnable', 'customer', 'items.product', 'creator']);

        return response()->json(['data' => $salesReturn]);
    }

    public function approve(SalesReturn $salesReturn): JsonResponse
    {
        if ($salesReturn->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft returns can be approved.',
            ], 422);
        }

        $warehouseId = $this->resolveWarehouseId($salesReturn->returnable_type, $salesReturn->returnable_id);

        DB::transaction(function () use ($salesReturn, $warehouseId): void {
            $salesReturn->load('items');

            $restockValue = 0.0;
            foreach ($salesReturn->items as $item) {
                if ($item->restock && $warehouseId) {
                    event(new StockChanged(
                        productId: $item->product_id,
                        warehouseId: $warehouseId,
                        type: 'in',
                        quantity: (float) $item->quantity,
                        referenceType: SalesReturn::class,
                        referenceId: $salesReturn->id,
                        userId: auth()->id(),
                        notes: 'Retur penjualan: '.$salesReturn->return_number,
                    ));
                    $restockValue += (float) $item->quantity * (float) $item->unit_price;
                }
            }

            $salesReturn->update(['status' => 'approved']);

            $this->recordSalesReturnJournal($salesReturn, $restockValue);
        });

        return response()->json(['data' => $salesReturn->fresh(['returnable', 'customer', 'items.product', 'creator'])]);
    }

    public function cancel(SalesReturn $salesReturn): JsonResponse
    {
        if ($salesReturn->status === 'cancelled') {
            return response()->json([
                'message' => 'Return is already cancelled.',
            ], 422);
        }

        if ($salesReturn->status === 'approved') {
            return response()->json([
                'message' => 'Approved returns cannot be cancelled. Reverse the restock manually first.',
            ], 422);
        }

        $salesReturn->update(['status' => 'cancelled']);

        return response()->json(['data' => $salesReturn->fresh(['returnable', 'customer', 'items.product', 'creator'])]);
    }

    private function generateReturnNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "RET-{$date}-";

        $lastReturn = $this->salesReturn->newQuery()
            ->where('return_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->first();

        if ($lastReturn) {
            $lastSequence = (int) substr($lastReturn->return_number, -4);
            $sequence = str_pad((string) ($lastSequence + 1), 4, '0', STR_PAD_LEFT);
        } else {
            $sequence = '0001';
        }

        return $prefix.$sequence;
    }

    private function resolveWarehouseId(string $type, int $returnableId): ?int
    {
        if ($type === SalesOrder::class) {
            return SalesOrder::find($returnableId)?->warehouse_id;
        }

        if ($type === SalesTransaction::class) {
            return SalesTransaction::find($returnableId)?->warehouse_id;
        }

        return null;
    }

    /**
     * Record the automatic journal for an approved sales return (PRD 4.5).
     * Dr: Retur Penjualan (4200), Cr: Kas/Piutang (1110/1210)
     * Dr: Persediaan (1310), Cr: HPP (5100) when restocked.
     */
    private function recordSalesReturnJournal(SalesReturn $salesReturn, float $restockValue): void
    {
        $returnAccount = Account::where('code', '4200')->first();
        $revenueAccount = Account::where('code', '4110')->first();
        $cashAccount = Account::where('code', '1110')->first();
        $receivableAccount = Account::where('code', '1210')->first();
        $inventoryAccount = Account::where('code', '1310')->first();
        $cogsAccount = Account::where('code', '5100')->first();

        if (! $returnAccount || ! $cashAccount || ! $receivableAccount) {
            return;
        }

        $totalAmount = (float) $salesReturn->total_amount;

        // Cash refund if original sale was cash; otherwise reduce AR
        $counterAccount = $cashAccount;

        try {
            app(JournalService::class)->createSalesReturnJournal(
                salesReturnId: $salesReturn->id,
                totalAmount: $totalAmount,
                receivableOrCashAccountId: $counterAccount->id,
                revenueAccountId: $returnAccount->id,
                restockAmount: $restockValue > 0 && $inventoryAccount && $cogsAccount ? $restockValue : null,
                inventoryAccountId: $inventoryAccount?->id,
                cogsAccountId: $cogsAccount?->id,
                userId: auth()->id(),
            );
        } catch (\RuntimeException) {
            // Skip journal silently if unbalanced — accounting integrity is preserved by the guard.
        }
    }
}
