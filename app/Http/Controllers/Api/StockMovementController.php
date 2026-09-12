<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function __construct() {}

    public function index(Request $request): JsonResponse
    {
        $query = StockMovement::with(['product', 'warehouse', 'creator']);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('movement_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('movement_date', '<=', $request->input('date_to'));
        }

        $items = $query->orderByDesc('movement_date')->paginate($request->integer('per_page', 15));

        return response()->json($items);
    }

    public function show(StockMovement $stockMovement): JsonResponse
    {
        $stockMovement->load(['product', 'warehouse', 'creator']);

        return response()->json(['data' => $stockMovement]);
    }
}
