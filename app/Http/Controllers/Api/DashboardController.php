<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private ReportService $reportService) {}

    public function index(Request $request): JsonResponse
    {
        $warehouseId = $request->integer('warehouse_id');

        return response()->json([
            'stats' => $this->reportService->getDashboardStats($warehouseId),
        ]);
    }
}
