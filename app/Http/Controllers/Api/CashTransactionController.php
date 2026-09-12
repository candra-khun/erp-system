<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CashTransactionResource;
use App\Models\CashTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CashTransactionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CashTransaction::with(['warehouse', 'creator']);

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('transaction_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $transactions = $query->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return CashTransactionResource::collection($transactions);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transaction_number' => 'required|string|unique:cash_transactions,transaction_number',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'type' => 'required|in:in,out',
            'category' => 'required|in:sales,purchase,operational,salary,other',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|string|max:50',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'transaction_date' => 'required|date',
        ]);

        $validated['created_by'] = auth()->id();

        $transaction = CashTransaction::create($validated);

        return (new CashTransactionResource($transaction))
            ->response()
            ->setStatusCode(201);
    }

    public function show(CashTransaction $cashTransaction): CashTransactionResource
    {
        $cashTransaction->load(['warehouse', 'creator']);

        return new CashTransactionResource($cashTransaction);
    }

    public function update(Request $request, CashTransaction $cashTransaction): CashTransactionResource
    {
        $validated = $request->validate([
            'transaction_number' => 'required|string|unique:cash_transactions,transaction_number,'.$cashTransaction->id,
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'type' => 'required|in:in,out',
            'category' => 'required|in:sales,purchase,operational,salary,other',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|string|max:50',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'transaction_date' => 'required|date',
        ]);

        $cashTransaction->update($validated);

        return new CashTransactionResource($cashTransaction->fresh());
    }

    public function destroy(CashTransaction $cashTransaction): JsonResponse
    {
        $cashTransaction->delete();

        return response()->json(null, 204);
    }
}
