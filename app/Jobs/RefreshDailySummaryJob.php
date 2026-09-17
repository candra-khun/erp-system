<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Warehouse;
use App\Services\ReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshDailySummaryJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?string $date = null) {}

    /**
     * Recalculate daily summary rows (global + per warehouse) for the given date.
     */
    public function handle(ReportService $reportService): void
    {
        $date = $this->date ?? now()->toDateString();

        $reportService->refreshDailySummary($date);

        Warehouse::query()
            ->where('is_active', true)
            ->pluck('id')
            ->each(fn (int $warehouseId) => $reportService->refreshDailySummary($date, $warehouseId));
    }
}
