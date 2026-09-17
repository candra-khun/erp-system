<?php

use App\Jobs\CheckReorderAlertsJob;
use App\Jobs\RefreshDailySummaryJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// PRD 4.2 — notifikasi otomatis stok rendah (Should Have).
Schedule::job(new CheckReorderAlertsJob)
    ->dailyAt('07:00')
    ->name('reorder-alerts-check')
    ->withoutOverlapping();

// PRD 4.6 — ringkasan laporan harian untuk dashboard.
Schedule::job(new RefreshDailySummaryJob)
    ->dailyAt('23:55')
    ->name('daily-summary-refresh')
    ->withoutOverlapping();
