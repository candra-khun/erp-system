<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Exports\ReportTableExport;
use App\Models\Warehouse;
use App\Services\ReportService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Layout('components.layouts.erp', ['title' => 'Laporan Penjualan'])]
class SalesReport extends Component
{
    #[Url]
    public string $startDate = '';

    #[Url]
    public string $endDate = '';

    #[Url]
    public string $warehouseId = '';

    #[Url]
    public string $groupBy = 'product';

    public function mount(): void
    {
        if (empty($this->startDate)) {
            $this->startDate = now()->startOfMonth()->format('Y-m-d');
        }
        if (empty($this->endDate)) {
            $this->endDate = now()->endOfMonth()->format('Y-m-d');
        }
    }

    public function render(): View
    {
        $service = app(ReportService::class);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $wId = $this->warehouseId !== '' ? (int) $this->warehouseId : null;

        $data = match ($this->groupBy) {
            'warehouse' => $service->getSalesByWarehouse($this->startDate, $this->endDate),
            'cashier' => $service->getSalesByCashier($this->startDate, $this->endDate, $wId),
            default => $service->getSalesByProduct($this->startDate, $this->endDate, $wId),
        };

        return view('livewire.sales-report', [
            'warehouses' => $warehouses,
            'data' => $data,
        ]);
    }

    public function exportExcel(): BinaryFileResponse
    {
        [$headings, $rows] = $this->buildExportData();

        $filename = 'laporan-penjualan-'.$this->groupBy.'-'.$this->startDate.'-sampai-'.$this->endDate.'.xlsx';

        return Excel::download(
            new ReportTableExport($headings, $rows, 'Laporan Penjualan'),
            $filename
        );
    }

    /**
     * @return array{0: list<string>, 1: array<int, array<int, mixed>>}
     */
    private function buildExportData(): array
    {
        $service = app(ReportService::class);
        $wId = $this->warehouseId !== '' ? (int) $this->warehouseId : null;

        return match ($this->groupBy) {
            'warehouse' => [
                ['Gudang', 'Jumlah Transaksi', 'Total Pendapatan'],
                array_map(
                    fn ($row) => [$row->warehouse, (int) $row->transaction_count, (float) $row->total_revenue],
                    $service->getSalesByWarehouse($this->startDate, $this->endDate)
                ),
            ],
            'cashier' => [
                ['Kasir', 'Jumlah Transaksi', 'Total Pendapatan'],
                array_map(
                    fn ($row) => [$row->cashier, (int) $row->transaction_count, (float) $row->total_revenue],
                    $service->getSalesByCashier($this->startDate, $this->endDate, $wId)
                ),
            ],
            default => [
                ['SKU', 'Nama Produk', 'Total Qty', 'Total Pendapatan'],
                array_map(
                    fn ($row) => [$row->sku, $row->name, (float) $row->total_qty, (float) $row->total_revenue],
                    $service->getSalesByProduct($this->startDate, $this->endDate, $wId)
                ),
            ],
        };
    }
}
