<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockTransfer;
use App\Services\StockTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockTransferController extends Controller
{
    public function __construct(
        private readonly StockTransferService $transferService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = StockTransfer::with([
            'items.product',
            'sourceWarehouse',
            'destinationWarehouse',
            'creator',
            'approver',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('source_warehouse_id')) {
            $query->where('source_warehouse_id', $request->input('source_warehouse_id'));
        }

        if ($request->filled('destination_warehouse_id')) {
            $query->where('destination_warehouse_id', $request->input('destination_warehouse_id'));
        }

        $items = $query->orderByDesc('created_at')->paginate($request->integer('per_page', 15));

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_warehouse_id' => 'required|exists:warehouses,id',
            'destination_warehouse_id' => 'required|exists:warehouses,id|different:source_warehouse_id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        $transfer = $this->transferService->createTransfer(
            $validated,
            $validated['items'],
            auth()->id(),
        );

        return response()->json(['data' => $transfer], 201);
    }

    public function show(StockTransfer $stockTransfer): JsonResponse
    {
        $stockTransfer->load([
            'items.product',
            'sourceWarehouse',
            'destinationWarehouse',
            'creator',
            'approver',
        ]);

        return response()->json(['data' => $stockTransfer]);
    }

    public function approve(StockTransfer $stockTransfer): JsonResponse
    {
        try {
            $transfer = $this->transferService->approve($stockTransfer, (int) auth()->id());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $transfer]);
    }

    public function ship(StockTransfer $stockTransfer): JsonResponse
    {
        try {
            $transfer = $this->transferService->ship($stockTransfer, (int) auth()->id());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $transfer]);
    }

    public function receive(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.stock_transfer_item_id' => 'required|integer|exists:stock_transfer_items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        try {
            $transfer = $this->transferService->receive($stockTransfer, $validated['items'], (int) auth()->id());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $transfer]);
    }

    public function cancel(StockTransfer $stockTransfer): JsonResponse
    {
        try {
            $transfer = $this->transferService->cancel($stockTransfer);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $transfer]);
    }
}
