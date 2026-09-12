<?php

namespace App\Services;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\CashTransaction;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\ReportDailySummary;
use App\Models\SalesOrder;
use App\Models\SalesReturn;
use App\Models\SalesTransaction;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Get dashboard summary statistics.
     */
    public function getDashboardStats(?int $warehouseId = null): array
    {
        $today = now()->toDateString();
        $thisMonth = now()->startOfMonth()->toDateString();

        $salesQuery = SalesTransaction::where('status', 'completed');
        $purchaseQuery = PurchaseOrder::where('status', 'approved');
        $apQuery = AccountPayable::where('status', '!=', 'paid');
        $arQuery = AccountReceivable::where('status', '!=', 'paid');
        $cashInQuery = CashTransaction::where('type', 'in');
        $cashOutQuery = CashTransaction::where('type', 'out');

        if ($warehouseId) {
            $salesQuery->where('warehouse_id', $warehouseId);
            $purchaseQuery->where('warehouse_id', $warehouseId);
            $apQuery->where('warehouse_id', $warehouseId);
            $arQuery->where('warehouse_id', $warehouseId);
            $cashInQuery->where('warehouse_id', $warehouseId);
            $cashOutQuery->where('warehouse_id', $warehouseId);
        }

        return [
            'today_sales' => (clone $salesQuery)->whereDate('created_at', $today)->sum('total_amount'),
            'monthly_sales' => (clone $salesQuery)->whereDate('created_at', '>=', $thisMonth)->sum('total_amount'),
            'today_transactions' => (clone $salesQuery)->whereDate('created_at', $today)->count(),
            'pending_ap' => (clone $apQuery)->sum('remaining_amount'),
            'pending_ar' => (clone $arQuery)->sum('remaining_amount'),
            'cash_in_today' => (clone $cashInQuery)->whereDate('transaction_date', $today)->sum('amount'),
            'cash_out_today' => (clone $cashOutQuery)->whereDate('transaction_date', $today)->sum('amount'),
            'low_stock_count' => Stock::join('products', 'stocks.product_id', '=', 'products.id')
                ->whereColumn('stocks.quantity', '<=', 'products.reorder_point')
                ->where('products.reorder_alert_enabled', true)
                ->when($warehouseId, fn ($q) => $q->where('stocks.warehouse_id', $warehouseId))
                ->distinct('stocks.product_id')
                ->count('stocks.product_id'),
            'total_products' => Product::where('is_active', true)->count(),
        ];
    }

    /**
     * Refresh daily summary cache for a given date and optional warehouse.
     */
    public function refreshDailySummary(string $date, ?int $warehouseId = null): ReportDailySummary
    {
        $baseSales = SalesTransaction::where('status', 'completed')->whereDate('created_at', $date);
        $basePurchases = GoodsReceipt::whereDate('receipt_date', $date);
        $baseCashIn = CashTransaction::where('type', 'in')->whereDate('transaction_date', $date);
        $baseCashOut = CashTransaction::where('type', 'out')->whereDate('transaction_date', $date);
        $baseSalesReturns = SalesReturn::where('status', 'approved')->whereDate('return_date', $date);
        $basePurchaseReturns = PurchaseReturn::where('status', 'approved')->whereDate('return_date', $date);

        if ($warehouseId) {
            $baseSales->where('warehouse_id', $warehouseId);
            $baseSalesReturns->whereHasMorph(
                'returnable',
                [SalesOrder::class, SalesTransaction::class],
                fn ($q) => $q->where('warehouse_id', $warehouseId),
            );
            $basePurchaseReturns->whereHas('purchaseOrder', fn ($q) => $q->where('warehouse_id', $warehouseId));
            $baseCashIn->where('warehouse_id', $warehouseId);
            $baseCashOut->where('warehouse_id', $warehouseId);
        }

        // GRN value is derived from its items (no total_amount column on goods_receipts)
        $purchasesQuery = DB::table('goods_receipt_items as gri')
            ->join('goods_receipts as gr', 'gri.goods_receipt_id', '=', 'gr.id')
            ->join('purchase_order_items as poi', 'gri.purchase_order_item_id', '=', 'poi.id')
            ->whereDate('gr.receipt_date', $date);
        if ($warehouseId) {
            $purchasesQuery->where('gr.warehouse_id', $warehouseId);
        }
        $totalPurchases = (float) $purchasesQuery->sum(DB::raw('gri.quantity * poi.unit_price'));

        return ReportDailySummary::updateOrCreate(
            ['summary_date' => $date, 'warehouse_id' => $warehouseId],
            [
                'total_sales' => (clone $baseSales)->sum('total_amount'),
                'total_purchases' => $totalPurchases,
                'total_returns' => (clone $baseSalesReturns)->sum('total_amount')
                    + (clone $basePurchaseReturns)->sum('total_amount'),
                'transaction_count' => (clone $baseSales)->count(),
                'cash_in' => (clone $baseCashIn)->sum('amount'),
                'cash_out' => (clone $baseCashOut)->sum('amount'),
            ]
        );
    }

    /**
     * Get sales report by product for a date range.
     */
    public function getSalesByProduct(string $startDate, string $endDate, ?int $warehouseId = null): array
    {
        $query = DB::table('sales_transaction_items as sti')
            ->join('sales_transactions as st', 'sti.sales_transaction_id', '=', 'st.id')
            ->join('products as p', 'sti.product_id', '=', 'p.id')
            ->whereBetween('st.created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->where('st.status', 'completed');

        if ($warehouseId) {
            $query->where('st.warehouse_id', $warehouseId);
        }

        return $query->selectRaw('p.id, p.sku, p.name, SUM(sti.quantity) as total_qty, SUM(sti.subtotal) as total_revenue')
            ->groupBy('p.id', 'p.sku', 'p.name')
            ->orderByDesc('total_revenue')
            ->get()
            ->toArray();
    }

    /**
     * Get sales report grouped by warehouse (branch) for a date range.
     *
     * @return array<int, object{warehouse_id: int, warehouse: string, transaction_count: int, total_revenue: float}>
     */
    public function getSalesByWarehouse(string $startDate, string $endDate): array
    {
        return DB::table('sales_transactions as st')
            ->join('warehouses as w', 'st.warehouse_id', '=', 'w.id')
            ->whereBetween('st.created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->where('st.status', 'completed')
            ->whereNull('st.deleted_at')
            ->selectRaw('w.id as warehouse_id, w.name as warehouse, COUNT(st.id) as transaction_count, SUM(st.total_amount) as total_revenue')
            ->groupBy('w.id', 'w.name')
            ->orderByDesc('total_revenue')
            ->get()
            ->toArray();
    }

    /**
     * Get sales report grouped by cashier (sales person) for a date range.
     *
     * @return array<int, object{cashier_id: int, cashier: string, transaction_count: int, total_revenue: float}>
     */
    public function getSalesByCashier(string $startDate, string $endDate, ?int $warehouseId = null): array
    {
        $query = DB::table('sales_transactions as st')
            ->join('users as u', 'st.cashier_id', '=', 'u.id')
            ->whereBetween('st.created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->where('st.status', 'completed')
            ->whereNull('st.deleted_at');

        if ($warehouseId) {
            $query->where('st.warehouse_id', $warehouseId);
        }

        return $query->selectRaw('u.id as cashier_id, u.name as cashier, COUNT(st.id) as transaction_count, SUM(st.total_amount) as total_revenue')
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('total_revenue')
            ->get()
            ->toArray();
    }

    /**
     * Get profit margin report per product.
     */
    public function getProfitMarginReport(string $startDate, string $endDate, ?int $warehouseId = null): array
    {
        $sales = $this->getSalesByProduct($startDate, $endDate, $warehouseId);

        foreach ($sales as &$item) {
            $avgCost = DB::table('goods_receipt_items as gri')
                ->join('goods_receipts as gr', 'gri.goods_receipt_id', '=', 'gr.id')
                ->join('purchase_order_items as poi', 'gri.purchase_order_item_id', '=', 'poi.id')
                ->where('gri.product_id', $item->id)
                ->whereBetween('gr.receipt_date', [$startDate, $endDate]);

            if ($warehouseId) {
                $avgCost->where('gr.warehouse_id', $warehouseId);
            }

            $avgUnitCost = $avgCost->avg('poi.unit_price') ?? 0;
            $item->avg_unit_cost = round((float) $avgUnitCost, 2);
            $item->margin = $item->total_revenue > 0
                ? round(($item->total_revenue - ($avgUnitCost * $item->total_qty)) / $item->total_revenue * 100, 2)
                : 0;
        }

        return $sales;
    }

    /**
     * Get balance sheet (neraca) as of a given date, derived from posted journal lines.
     * Asset = debit - credit; liability/equity/revenue = credit - debit; expense = debit - credit.
     *
     * @return array{
     *     as_of: string,
     *     assets: array<int, array{code: string, name: string, balance: float}>,
     *     liabilities: array<int, array{code: string, name: string, balance: float}>,
     *     equity: array<int, array{code: string, name: string, balance: float}>,
     *     total_assets: float,
     *     total_liabilities: float,
     *     total_equity_before_closing: float,
     *     retained_earnings: float,
     *     total_equity: float,
     *     total_liabilities_and_equity: float
     * }
     */
    public function getBalanceSheet(string $asOfDate): array
    {
        $balances = DB::table('accounts as a')
            ->leftJoin('journal_entry_lines as jel', function ($join) use ($asOfDate) {
                $join->on('jel.account_id', '=', 'a.id')
                    ->join('journal_entries as je', 'jel.journal_entry_id', '=', 'je.id')
                    ->where('je.journal_date', '<=', $asOfDate)
                    ->where('je.is_posted', true)
                    ->whereNull('je.deleted_at');
            })
            ->whereNull('a.deleted_at')
            ->where('a.is_active', true)
            ->selectRaw('a.id, a.code, a.name, a.type,
                SUM(CASE WHEN jel.type = "debit" THEN jel.amount ELSE 0 END) as total_debit,
                SUM(CASE WHEN jel.type = "credit" THEN jel.amount ELSE 0 END) as total_credit')
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type')
            ->get();

        $assets = [];
        $liabilities = [];
        $equity = [];
        $retainedEarnings = 0;

        foreach ($balances as $account) {
            $debit = (float) $account->total_debit;
            $credit = (float) $account->total_credit;

            switch ($account->type) {
                case 'asset':
                    $balance = $debit - $credit;
                    if (abs($balance) > 0.001) {
                        $assets[] = ['code' => $account->code, 'name' => $account->name, 'balance' => round($balance, 2)];
                    }
                    break;

                case 'liability':
                    $balance = $credit - $debit;
                    if (abs($balance) > 0.001) {
                        $liabilities[] = ['code' => $account->code, 'name' => $account->name, 'balance' => round($balance, 2)];
                    }
                    break;

                case 'equity':
                    $balance = $credit - $debit;
                    if (abs($balance) > 0.001) {
                        $equity[] = ['code' => $account->code, 'name' => $account->name, 'balance' => round($balance, 2)];
                    }
                    break;

                case 'revenue':
                    // Revenue balance flows into retained earnings (credit - debit)
                    $retainedEarnings += ($credit - $debit);
                    break;

                case 'expense':
                    // Expense reduces retained earnings (debit - credit)
                    $retainedEarnings -= ($debit - $credit);
                    break;
            }
        }

        $totalAssets = array_sum(array_column($assets, 'balance'));
        $totalLiabilities = array_sum(array_column($liabilities, 'balance'));
        $equityBeforeClosing = array_sum(array_column($equity, 'balance'));
        $totalEquity = $equityBeforeClosing + $retainedEarnings;

        return [
            'as_of' => $asOfDate,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'total_assets' => round($totalAssets, 2),
            'total_liabilities' => round($totalLiabilities, 2),
            'total_equity_before_closing' => round($equityBeforeClosing, 2),
            'retained_earnings' => round($retainedEarnings, 2),
            'total_equity' => round($totalEquity, 2),
            'total_liabilities_and_equity' => round($totalLiabilities + $totalEquity, 2),
        ];
    }
}
