<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Exports\ReportTableExport;
use App\Livewire\Concerns\InteractsWithWarehouseAccess;
use App\Services\ReportService;
use App\Support\WarehouseAccess;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Layout('components.layouts.erp', ['title' => 'Laporan Penjualan'])]
class SalesReport extends Component
{
    /** @use InteractsWithWarehouseAccess<self> */
    use InteractsWithWarehouseAccess;

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
        $warehouses = $this->accessibleWarehouseOptions();

        // Clamp filter gudang ke scope akses user (PRD §2: Admin Cabang = 1 cabang)
        $this->warehouseId = $this->clampWarehouseFilter($this->warehouseId);

        [, $scopedIds] = $this->resolveWarehouseScope();

        $data = match ($this->groupBy) {
            'warehouse' => $service->getSalesByWarehouse($this->startDate, $this->endDate, $scopedIds),
            'cashier' => $service->getSalesByCashier($this->startDate, $this->endDate, $scopedIds),
            default => $service->getSalesByProduct($this->startDate, $this->endDate, $scopedIds),
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
     * Resolve filter gudang yang valid + batas cabang yang boleh dilihat.
     *
     * @return array{0: int|null, 1: list<int>|null}
     */
    private function resolveWarehouseScope(): array
    {
        $wId = $this->warehouseId !== '' ? (int) $this->warehouseId : null;

        if ($wId !== null && ! WarehouseAccess::canAccess(auth()->user(), $wId)) {
            $wId = null;
        }

        if ($wId !== null) {
            return [$wId, [$wId]];
        }

        return [null, $this->accessibleWarehouseIds()];
    }

    /**
     * @return array{0: list<string>, 1: array<int, array<int, mixed>>}
     */
    private function buildExportData(): array
    {
        $service = app(ReportService::class);

        $this->warehouseId = $this->clampWarehouseFilter($this->warehouseId);
        [, $scopedIds] = $this->resolveWarehouseScope();

        return match ($this->groupBy) {
            'warehouse' => [
                ['Gudang', 'Jumlah Transaksi', 'Total Pendapatan'],
                array_map(
                    fn ($row) => [$row->warehouse, (int) $row->transaction_count, (float) $row->total_revenue],
                    $service->getSalesByWarehouse($this->startDate, $this->endDate, $scopedIds)
                ),
            ],
            'cashier' => [
                ['Kasir', 'Jumlah Transaksi', 'Total Pendapatan'],
                array_map(
                    fn ($row) => [$row->cashier, (int) $row->transaction_count, (float) $row->total_revenue],
                    $service->getSalesByCashier($this->startDate, $this->endDate, $scopedIds)
                ),
            ],
            default => [
                ['SKU', 'Nama Produk', 'Total Qty', 'Total Pendapatan'],
                array_map(
                    fn ($row) => [$row->sku, $row->name, (float) $row->total_qty, (float) $row->total_revenue],
                    $service->getSalesByProduct($this->startDate, $this->endDate, $scopedIds)
                ),
            ],
        };
    }
}
