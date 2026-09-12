<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PosShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PosShiftController extends Controller
{
    public function __construct(
        private readonly PosShift $posShift
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->posShift->newQuery()
            ->with(['warehouse', 'user']);

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $shifts = $query->latest('opened_at')->paginate($request->input('per_page', 15));

        return response()->json($shifts);
    }

    public function open(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'opening_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $userId = auth()->id();

        $existingOpenShift = $this->posShift->newQuery()
            ->where('user_id', $userId)
            ->where('warehouse_id', $validated['warehouse_id'])
            ->where('status', 'open')
            ->first();

        if ($existingOpenShift !== null) {
            return response()->json([
                'message' => 'You already have an open shift for this warehouse. Please close it first.',
                'data' => $existingOpenShift,
            ], 422);
        }

        $shift = $this->posShift->create([
            'warehouse_id' => $validated['warehouse_id'],
            'user_id' => $userId,
            'opened_at' => Carbon::now(),
            'opening_cash' => $validated['opening_cash'],
            'status' => 'open',
            'notes' => $validated['notes'] ?? null,
        ]);

        $shift->load(['warehouse', 'user']);

        return response()->json(['data' => $shift], 201);
    }

    public function close(Request $request, PosShift $posShift): JsonResponse
    {
        if ($posShift->status !== 'open') {
            return response()->json([
                'message' => 'Only open shifts can be closed.',
            ], 422);
        }

        $validated = $request->validate([
            'closing_cash' => ['required', 'numeric', 'min:0'],
        ]);

        $totalSales = $posShift->salesTransactions()
            ->where('status', 'completed')
            ->sum('total_amount');

        $expectedCash = (float) $posShift->opening_cash + (float) $totalSales;

        $posShift->update([
            'closing_cash' => $validated['closing_cash'],
            'expected_cash' => $expectedCash,
            'closed_at' => Carbon::now(),
            'status' => 'closed',
        ]);

        $posShift->load(['warehouse', 'user', 'salesTransactions']);

        return response()->json(['data' => $posShift]);
    }

    public function show(PosShift $posShift): JsonResponse
    {
        $posShift->load(['warehouse', 'user', 'salesTransactions.items.product']);

        return response()->json(['data' => $posShift]);
    }
}
