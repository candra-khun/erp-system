<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::with(['category', 'baseUnit']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('product_category_id', $request->input('category_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $products = $query->orderBy('name')->paginate($request->integer('per_page', 15));

        return ProductResource::collection($products);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => 'required|string|unique:products,sku',
            'name' => 'required|string|max:255',
            'product_category_id' => 'nullable|exists:product_categories,id',
            'base_unit_id' => 'nullable|exists:product_units,id',
            'barcode' => 'nullable|string|unique:products,barcode',
            'description' => 'nullable|string',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'min_stock' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $product = Product::create($validated);

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        $product->load(['category', 'baseUnit', 'stocks.warehouse']);

        return new ProductResource($product);
    }

    public function update(Request $request, Product $product): ProductResource
    {
        $validated = $request->validate([
            'sku' => 'sometimes|required|string|unique:products,sku,'.$product->id,
            'name' => 'sometimes|required|string|max:255',
            'product_category_id' => 'sometimes|nullable|exists:product_categories,id',
            'base_unit_id' => 'sometimes|nullable|exists:product_units,id',
            'barcode' => 'sometimes|nullable|string|unique:products,barcode,'.$product->id,
            'description' => 'nullable|string',
            'purchase_price' => 'sometimes|required|numeric|min:0',
            'selling_price' => 'sometimes|required|numeric|min:0',
            'min_stock' => 'sometimes|nullable|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $product->update($validated);

        return new ProductResource($product->fresh());
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(null, 204);
    }
}
