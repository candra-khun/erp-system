<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccountPayableResource;
use App\Models\AccountPayable;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountPayableController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = AccountPayable::with('supplier');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        $items = $query->orderByDesc('due_date')->paginate($request->integer('per_page', 15));

        return AccountPayableResource::collection($items);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ap_number' => 'required|string|unique:account_payables,ap_number',
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|integer',
            'total_amount' => 'required|numeric|min:0',
            'due_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $validated['remaining_amount'] = $validated['total_amount'];
        $validated['paid_amount'] = 0;
        $validated['status'] = 'open';

        $ap = AccountPayable::create($validated);

        return (new AccountPayableResource($ap))
            ->response()
            ->setStatusCode(201);
    }

    public function show(AccountPayable $accountPayable): AccountPayableResource
    {
        $accountPayable->load('supplier');

        return new AccountPayableResource($accountPayable);
    }

    public function update(Request $request, AccountPayable $accountPayable): AccountPayableResource
    {
        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $accountPayable->update($validated);

        return new AccountPayableResource($accountPayable->fresh());
    }

    /**
     * Record a payment against this payable (delta, not cumulative).
     * Creates cash-out transaction + automatic journal via FinanceService.
     */
    public function pay(Request $request, AccountPayable $accountPayable): JsonResponse|AccountPayableResource
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'nullable|string|max:50',
        ]);

        try {
            $result = app(FinanceService::class)->payPayable(
                $accountPayable,
                (float) $validated['amount'],
                $request->user()?->id,
                $validated['method'] ?? null,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new AccountPayableResource($result['payable']);
    }

    public function destroy(AccountPayable $accountPayable): JsonResponse
    {
        $accountPayable->delete();

        return response()->json(null, 204);
    }
}
