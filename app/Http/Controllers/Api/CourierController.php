<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Courier::when($request->filled('search'), function ($q) use ($request): void {
            $search = (string) $request->input('search');
            $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%");
        })->orderBy('name');

        return response()->json(['data' => $query->paginate($request->integer('per_page', 15))]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:couriers,code',
            'name' => 'required|string|max:150',
            'type' => 'required|in:internal,external',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:150',
            'address' => 'nullable|string|max:500',
            'cost_per_kg' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        return response()->json(['data' => Courier::create($validated)], 201);
    }

    public function show(Courier $courier): JsonResponse
    {
        return response()->json(['data' => $courier->loadCount('shipments')]);
    }

    public function update(Request $request, Courier $courier): JsonResponse
    {
        $validated = $request->validate([
            'code' => "required|string|max:20|unique:couriers,code,{$courier->id}",
            'name' => 'required|string|max:150',
            'type' => 'required|in:internal,external',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:150',
            'address' => 'nullable|string|max:500',
            'cost_per_kg' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $courier->update($validated);

        return response()->json(['data' => $courier->fresh()]);
    }

    public function destroy(Courier $courier): JsonResponse
    {
        $courier->delete();

        return response()->json(null, 204);
    }
}
