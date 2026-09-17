<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithWarehouseAccess;
use App\Models\CashTransaction;
use App\Models\SalesTransaction;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.erp', ['title' => 'Laporan Laba Rugi'])]
class ProfitLossReport extends Component
{
    /** @use InteractsWithWarehouseAccess<self> */
    use InteractsWithWarehouseAccess;

    #[Url]
    public string $startDate = '';

    #[Url]
    public string $endDate = '';

    #[Url]
    public string $warehouseId = '';

    public function mount(): void
    {
        if (empty($this->startDate)) {
            $this->startDate = now()->startOfMonth()->format('Y-m-d');
        }
        if (empty($this->endDate)) {
            $this->endDate = now()->endOfMonth()->format('Y-m-d');
        }
    }

    public function exportPdf()
    {
        $data = $this->calculateReport();

        // View PDF menerima variabel flat (bukan nested $report)
        $pdf = \PDF::loadView('reports.profit-loss-pdf', [
            'startDate' => $data['startDate'],
            'endDate' => $data['endDate'],
            'totalRevenue' => $data['totalRevenue'],
            'totalCogs' => $data['totalCogs'],
            'grossProfit' => $data['grossProfit'],
            'expenses' => $data['expenses'],
            'totalExpenses' => $data['totalExpenses'],
            'netProfit' => $data['netProfit'],
        ]);

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'laporan-laba-rugi-'.$this->startDate.'-sampai-'.$this->endDate.'.pdf'
        );
    }

    private function calculateReport(): array
    {
        $start = $this->startDate;
        $end = $this->endDate;
        $wId = $this->warehouseId ? (int) $this->warehouseId : null;

        // Total Pendapatan dari sales_transactions
        $salesQuery = SalesTransaction::where('status', 'completed')
            ->whereBetween('created_at', [$start.' 00:00:00', $end.' 23:59:59']);
        if ($wId) {
            $salesQuery->where('warehouse_id', $wId);
        }
        $totalRevenue = (float) $salesQuery->sum('total_amount');

        // Total HPP (COGS): harga pokok barang yang TERJUAL — bukan barang yang dibeli.
        // Per produk terjual dalam periode, harga pokok = moving-average unit cost
        // dari seluruh penerimaan barang (GRN) s.d. akhir periode (per gudang bila difilter).
        $soldItems = DB::table('sales_transaction_items as sti')
            ->join('sales_transactions as st', 'sti.sales_transaction_id', '=', 'st.id')
            ->where('st.status', 'completed')
            ->whereBetween('st.created_at', [$start.' 00:00:00', $end.' 23:59:59']);
        if ($wId) {
            $soldItems->where('st.warehouse_id', $wId);
        }
        $soldItems = $soldItems
            ->selectRaw('sti.product_id, SUM(sti.quantity) as total_qty')
            ->groupBy('sti.product_id')
            ->get();

        $totalCogs = 0.0;
        foreach ($soldItems as $sold) {
            $avgUnitCost = (float) DB::table('goods_receipt_items as gri')
                ->join('goods_receipts as gr', 'gri.goods_receipt_id', '=', 'gr.id')
                ->join('purchase_order_items as poi', 'gri.purchase_order_item_id', '=', 'poi.id')
                ->where('gri.product_id', $sold->product_id)
                ->when($wId, fn ($q) => $q->where('gr.warehouse_id', $wId))
                ->whereDate('gr.receipt_date', '<=', $end)
                ->avg('poi.unit_price');

            // Fallback untuk produk yang belum pernah diterima via GRN: pakai harga beli master
            if ($avgUnitCost <= 0) {
                $avgUnitCost = (float) DB::table('products')
                    ->where('id', $sold->product_id)
                    ->value('purchase_price');
            }

            $totalCogs += $avgUnitCost * (float) $sold->total_qty;
        }

        // Laba Kotor
        $grossProfit = $totalRevenue - $totalCogs;

        // Total Beban dari cash_transactions type=out, exclude category purchase (karena sudah di HPP)
        $expenseQuery = CashTransaction::where('type', 'out')
            ->whereNotIn('category', ['purchase'])
            ->whereBetween('transaction_date', [$start, $end]);
        if ($wId) {
            $expenseQuery->where('warehouse_id', $wId);
        }

        $expensesByCategory = $expenseQuery->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->pluck('total', 'category')
            ->toArray();

        $totalExpenses = (float) array_sum($expensesByCategory);

        // Laba Bersih
        $netProfit = $grossProfit - $totalExpenses;

        $categoryLabels = [
            'operational' => 'Beban Operasional',
            'salary' => 'Beban Gaji',
            'other' => 'Beban Lainnya',
            'sales' => 'Beban Penjualan',
        ];

        $formattedExpenses = [];
        foreach ($expensesByCategory as $cat => $amount) {
            $formattedExpenses[] = [
                'label' => $categoryLabels[$cat] ?? ucfirst($cat),
                'amount' => (float) $amount,
            ];
        }

        return [
            'startDate' => $start,
            'endDate' => $end,
            'totalRevenue' => $totalRevenue,
            'totalCogs' => $totalCogs,
            'grossProfit' => $grossProfit,
            'expenses' => $formattedExpenses,
            'totalExpenses' => $totalExpenses,
            'netProfit' => $netProfit,
        ];
    }

    public function render(): View
    {
        $warehouses = $this->accessibleWarehouseOptions();
        $report = $this->calculateReport();

        return view('livewire.profit-loss-report', [
            'warehouses' => $warehouses,
            'report' => $report,
        ]);
    }
}
