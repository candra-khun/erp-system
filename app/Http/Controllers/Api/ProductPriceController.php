<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductPriceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ProductPrice::with(['product', 'warehouse']);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->filled('price_type')) {
            $query->where('price_type', $request->input('price_type'));
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $prices = $query->orderBy('product_id')->paginate($request->integer('per_page', 15));

        return response()->json($prices);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'price_type' => 'required|string|max:50',
            'price' => 'required|numeric|min:0',
            'min_quantity' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        $price = ProductPrice::create($validated);

        return response()->json(['data' => $price->load(['product', 'warehouse'])], 201);
    }

    public function show(ProductPrice $productPrice): JsonResponse
    {
        $productPrice->load(['product', 'warehouse']);

        return response()->json(['data' => $productPrice]);
    }

    public function update(Request $request, ProductPrice $productPrice): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'sometimes|required|exists:products,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'price_type' => 'sometimes|required|string|max:50',
            'price' => 'sometimes|required|numeric|min:0',
            'min_quantity' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        $productPrice->update($validated);

        return response()->json(['data' => $productPrice->fresh(['product', 'warehouse'])]);
    }

    public function destroy(ProductPrice $productPrice): JsonResponse
    {
        $productPrice->delete();

        return response()->json(null, 204);
    }
}
