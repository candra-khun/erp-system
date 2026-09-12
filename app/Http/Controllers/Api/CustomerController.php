<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Customer::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $customers = $query->orderBy('name')->paginate($request->integer('per_page', 15));

        return CustomerResource::collection($customers);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:customers,code',
            'name' => 'required|string|max:255',
            'type' => 'required|in:general,member,reseller',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'province' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'credit_limit' => 'integer|min:0',
            'payment_terms_days' => 'integer|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $customer = Customer::create($validated);

        return (new CustomerResource($customer))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Customer $customer): CustomerResource
    {
        return new CustomerResource($customer);
    }

    public function update(Request $request, Customer $customer): CustomerResource
    {
        $validated = $request->validate([
            'code' => 'sometimes|required|string|unique:customers,code,'.$customer->id,
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:general,member,reseller',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'province' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'credit_limit' => 'sometimes|integer|min:0',
            'payment_terms_days' => 'sometimes|integer|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $customer->update($validated);

        return new CustomerResource($customer->fresh());
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return response()->json(null, 204);
    }
}
