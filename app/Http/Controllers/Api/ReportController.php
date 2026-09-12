<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private ReportService $reportService) {}

    public function salesByProduct(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
        ]);

        $data = $this->reportService->getSalesByProduct(
            $validated['start_date'],
            $validated['end_date'],
            $validated['warehouse_id'] ?? null,
        );

        return response()->json(['data' => $data]);
    }

    public function salesByWarehouse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $data = $this->reportService->getSalesByWarehouse(
            $validated['start_date'],
            $validated['end_date'],
        );

        return response()->json(['data' => $data]);
    }

    public function salesByCashier(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
        ]);

        $data = $this->reportService->getSalesByCashier(
            $validated['start_date'],
            $validated['end_date'],
            $validated['warehouse_id'] ?? null,
        );

        return response()->json(['data' => $data]);
    }

    public function balanceSheet(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'as_of_date' => 'required|date',
        ]);

        $data = $this->reportService->getBalanceSheet($validated['as_of_date']);

        return response()->json(['data' => $data]);
    }

    public function profitMargin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
        ]);

        $data = $this->reportService->getProfitMarginReport(
            $validated['start_date'],
            $validated['end_date'],
            $validated['warehouse_id'] ?? null,
        );

        return response()->json(['data' => $data]);
    }

    public function refreshDailySummary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
        ]);

        $summary = $this->reportService->refreshDailySummary(
            $validated['date'],
            $validated['warehouse_id'] ?? null,
        );

        return response()->json(['data' => $summary]);
    }
}
