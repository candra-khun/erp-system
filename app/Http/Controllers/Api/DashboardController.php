<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ScopesWarehouseAccess;
use App\Http\Controllers\Controller;
use App\Rules\WarehouseAccessible;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ScopesWarehouseAccess;

    public function __construct(private ReportService $reportService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id', new WarehouseAccessible],
        ]);

        return response()->json([
            'stats' => $this->reportService->getDashboardStats(
                $this->resolveWarehouseScope($validated['warehouse_id'] ?? null),
            ),
        ]);
    }
}
