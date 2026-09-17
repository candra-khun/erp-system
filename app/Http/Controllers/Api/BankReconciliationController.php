<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankReconciliation;
use App\Models\BankStatementLine;
use App\Services\BankReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankReconciliationController extends Controller
{
    public function __construct(private readonly BankReconciliationService $service) {}

    public function index(Request $request): JsonResponse
    {
        $query = BankReconciliation::with('warehouse')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByDesc('id');

        return response()->json(['data' => $query->paginate($request->integer('per_page', 15))]);
    }

    /**
     * Create a session with statement lines. Lines auto-matched on creation.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'bank_statement_balance' => 'required|numeric',
            'notes' => 'nullable|string|max:500',
            'lines' => 'required|array|min:1',
            'lines.*.value_date' => 'required|date',
            'lines.*.description' => 'nullable|string|max:255',
            'lines.*.amount' => 'required|numeric',
            'lines.*.bank_reference' => 'nullable|string|max:100',
        ]);

        try {
            $session = $this->service->createSession(
                $validated,
                $validated['lines'],
                (int) $request->user()?->id,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $session], 201);
    }

    public function show(BankReconciliation $bankReconciliation): JsonResponse
    {
        return response()->json([
            'data' => $bankReconciliation->load(['statementLines.matchedTransaction', 'warehouse']),
        ]);
    }

    /**
     * Manually match a statement line to a cash transaction (or unmatch).
     */
    public function matchLine(Request $request, BankStatementLine $line): JsonResponse
    {
        $validated = $request->validate([
            'cash_transaction_id' => 'nullable|exists:cash_transactions,id',
        ]);

        try {
            $line = $this->service->setMatch($line, $validated['cash_transaction_id'] ?? null);
            $this->service->recomputeBalances($line->reconciliation);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $line->fresh()->load('matchedTransaction')]);
    }

    /**
     * Re-run auto-matching for an open session.
     */
    public function rematch(BankReconciliation $bankReconciliation): JsonResponse
    {
        $matched = $this->service->autoMatch($bankReconciliation);
        $this->service->recomputeBalances($bankReconciliation);

        return response()->json(['data' => ['matched_count' => $matched]]);
    }

    public function complete(BankReconciliation $bankReconciliation): JsonResponse
    {
        try {
            $session = $this->service->complete($bankReconciliation, (int) auth()->id());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $session]);
    }

    public function cancel(BankReconciliation $bankReconciliation): JsonResponse
    {
        if ($bankReconciliation->status === 'completed') {
            return response()->json(['message' => 'Rekonsiliasi yang sudah selesai tidak dapat dibatalkan.'], 422);
        }

        $bankReconciliation->update(['status' => 'cancelled']);

        return response()->json(['data' => $bankReconciliation->fresh()]);
    }
}
