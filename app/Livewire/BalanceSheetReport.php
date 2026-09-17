<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\ReportService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.erp', ['title' => 'Neraca'])]
class BalanceSheetReport extends Component
{
    #[Url]
    public string $asOfDate = '';

    public function mount(): void
    {
        if (empty($this->asOfDate)) {
            $this->asOfDate = now()->format('Y-m-d');
        }
    }

    public function exportPdf(ReportService $reportService)
    {
        $report = $reportService->getBalanceSheet($this->asOfDate);

        $pdf = \PDF::loadView('reports.balance-sheet-pdf', [
            'asOfDate' => $report['as_of'],
            'assets' => $report['assets'],
            'liabilities' => $report['liabilities'],
            'equity' => $report['equity'],
            'totalAssets' => $report['total_assets'],
            'totalLiabilities' => $report['total_liabilities'],
            'totalEquityBeforeClosing' => $report['total_equity_before_closing'],
            'retainedEarnings' => $report['retained_earnings'],
            'totalEquity' => $report['total_equity'],
            'totalLiabilitiesAndEquity' => $report['total_liabilities_and_equity'],
        ]);

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'neraca-'.$this->asOfDate.'.pdf'
        );
    }

    public function render(ReportService $reportService): View
    {
        $report = $reportService->getBalanceSheet($this->asOfDate);

        return view('livewire.balance-sheet-report', [
            'report' => $report,
        ]);
    }
}
