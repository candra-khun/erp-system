<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccountReceivableResource;
use App\Models\AccountReceivable;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountReceivableController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = AccountReceivable::with('customer');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        $items = $query->orderByDesc('due_date')->paginate($request->integer('per_page', 15));

        return AccountReceivableResource::collection($items);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ar_number' => 'required|string|unique:account_receivables,ar_number',
            'customer_id' => 'required|exists:customers,id',
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

        $ar = AccountReceivable::create($validated);

        return (new AccountReceivableResource($ar))
            ->response()
            ->setStatusCode(201);
    }

    public function show(AccountReceivable $accountReceivable): AccountReceivableResource
    {
        $accountReceivable->load('customer');

        return new AccountReceivableResource($accountReceivable);
    }

    public function update(Request $request, AccountReceivable $accountReceivable): AccountReceivableResource
    {
        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $accountReceivable->update($validated);

        return new AccountReceivableResource($accountReceivable->fresh());
    }

    /**
     * Record a payment received against this receivable (delta, not cumulative).
     * Creates cash-in transaction + automatic journal via FinanceService.
     */
    public function pay(Request $request, AccountReceivable $accountReceivable): JsonResponse|AccountReceivableResource
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'nullable|string|max:50',
        ]);

        try {
            $result = app(FinanceService::class)->collectReceivable(
                $accountReceivable,
                (float) $validated['amount'],
                $request->user()?->id,
                $validated['method'] ?? null,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new AccountReceivableResource($result['receivable']);
    }

    public function destroy(AccountReceivable $accountReceivable): JsonResponse
    {
        $accountReceivable->delete();

        return response()->json(null, 204);
    }
}
