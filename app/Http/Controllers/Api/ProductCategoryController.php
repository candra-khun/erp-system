<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCategoryResource;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class ProductCategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ProductCategory::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }

        if ($request->filled('parent_id')) {
            $query->where('parent_id', $request->input('parent_id'));
        } elseif ($request->boolean('root_only')) {
            $query->whereNull('parent_id');
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $categories = $query->with('children')->orderBy('sort_order')->orderBy('name')->get();

        return ProductCategoryResource::collection($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:product_categories,id',
            'slug' => 'nullable|string|unique:product_categories,slug',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $category = ProductCategory::create($validated);

        return (new ProductCategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ProductCategory $productCategory): ProductCategoryResource
    {
        $productCategory->load(['children', 'products']);

        return new ProductCategoryResource($productCategory);
    }

    public function update(Request $request, ProductCategory $productCategory): ProductCategoryResource
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:product_categories,id',
            'slug' => 'nullable|string|unique:product_categories,slug,'.$productCategory->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $productCategory->update($validated);

        return new ProductCategoryResource($productCategory->fresh());
    }

    public function destroy(ProductCategory $productCategory): JsonResponse
    {
        $productCategory->delete();

        return response()->json(null, 204);
    }
}
