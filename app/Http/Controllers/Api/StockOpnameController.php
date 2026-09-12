<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Stock;
use App\Models\StockOpname;
use App\Services\StockOpnameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockOpnameController extends Controller
{
    public function __construct(
        private readonly StockOpnameService $opnameService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = StockOpname::with(['warehouse', 'creator']);

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $items = $query->orderByDesc('created_at')->paginate($request->integer('per_page', 15));

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.physical_quantity' => 'required|numeric|min:0',
        ]);

        $opname = DB::transaction(function () use ($validated): StockOpname {
            $opname = StockOpname::create([
                'opname_number' => $this->generateOpnameNumber(),
                'warehouse_id' => $validated['warehouse_id'],
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                $stock = Stock::where('product_id', $item['product_id'])
                    ->where('warehouse_id', $validated['warehouse_id'])
                    ->first();

                $systemQuantity = $stock?->quantity ?? 0;
                $difference = $item['physical_quantity'] - $systemQuantity;

                $opname->items()->create([
                    'product_id' => $item['product_id'],
                    'system_quantity' => $systemQuantity,
                    'physical_quantity' => $item['physical_quantity'],
                    'difference' => $difference,
                ]);
            }

            return $opname;
        });

        $opname->load(['items.product', 'warehouse', 'creator']);

        return response()->json(['data' => $opname], 201);
    }

    public function show(StockOpname $stockOpname): JsonResponse
    {
        $stockOpname->load(['items.product', 'warehouse', 'creator']);

        return response()->json(['data' => $stockOpname]);
    }

    public function approve(StockOpname $stockOpname): JsonResponse
    {
        if (! in_array($stockOpname->status, ['draft', 'completed'], true)) {
            return response()->json([
                'message' => 'Only draft or completed opnames can be approved. Current status: '.$stockOpname->status,
            ], 422);
        }

        $this->opnameService->approve($stockOpname, auth()->id());

        $stockOpname->load(['items.product', 'warehouse', 'creator', 'approver']);

        return response()->json(['data' => $stockOpname]);
    }

    private function generateOpnameNumber(): string
    {
        $prefix = 'OPN-'.now()->format('Ymd').'-';

        $lastOpname = StockOpname::withTrashed()
            ->where('opname_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->first();

        if ($lastOpname) {
            $lastSequence = (int) substr($lastOpname->opname_number, -4);
            $sequence = str_pad((string) ($lastSequence + 1), 4, '0', STR_PAD_LEFT);
        } else {
            $sequence = '0001';
        }

        return $prefix.$sequence;
    }
}
