<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WarehouseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Warehouse::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $warehouses = $query->orderBy('name')->paginate($request->integer('per_page', 15));

        return WarehouseResource::collection($warehouses);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:warehouses,code',
            'name' => 'required|string|max:255',
            'type' => 'required|in:warehouse,branch,store',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'province' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'manager_name' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $warehouse = Warehouse::create($validated);

        return (new WarehouseResource($warehouse))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Warehouse $warehouse): WarehouseResource
    {
        return new WarehouseResource($warehouse);
    }

    public function update(Request $request, Warehouse $warehouse): WarehouseResource
    {
        $validated = $request->validate([
            'code' => 'sometimes|required|string|unique:warehouses,code,'.$warehouse->id,
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:warehouse,branch,store',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'province' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'manager_name' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $warehouse->update($validated);

        return new WarehouseResource($warehouse->fresh());
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $warehouse->delete();

        return response()->json(null, 204);
    }
}
