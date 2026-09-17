<?php

use App\Http\Controllers\MasterDataTemplateController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\ProfileController;
use App\Livewire\AccountPayableList;
use App\Livewire\AccountReceivableList;
use App\Livewire\BalanceSheetReport;
use App\Livewire\BankReconciliationList;
use App\Livewire\CashTransactionList;
use App\Livewire\CategoryList;
use App\Livewire\ChartOfAccounts;
use App\Livewire\ConsignmentList;
use App\Livewire\ConsignmentSettlementList;
use App\Livewire\CustomerList;
use App\Livewire\Dashboard;
use App\Livewire\EmployeeList;
use App\Livewire\GatewayTransactionList;
use App\Livewire\GoodsReceiptList;
use App\Livewire\InventoryTurnoverReport;
use App\Livewire\JournalEntryList;
use App\Livewire\MarketplaceChannelList;
use App\Livewire\MarketplaceOrderList;
use App\Livewire\PayrollList;
use App\Livewire\PosTerminal;
use App\Livewire\ProductList;
use App\Livewire\ProfitLossReport;
use App\Livewire\PurchaseOrderList;
use App\Livewire\PurchaseReturnList;
use App\Livewire\ReorderAlertList;
use App\Livewire\SalesOrderList;
use App\Livewire\SalesReport;
use App\Livewire\SalesReturnList;
use App\Livewire\ShipmentList;
use App\Livewire\StockCard;
use App\Livewire\StockList;
use App\Livewire\StockOpnameList;
use App\Livewire\StockTransferList;
use App\Livewire\SupplierList;
use App\Livewire\WarehouseList;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    // Master Data
    Route::middleware('permission:manage-products')->group(function () {
        Route::get('/products', ProductList::class)->name('products.index');
        Route::get('/categories', CategoryList::class)->name('categories.index');
        Route::get('/master-data/import-template/products', [MasterDataTemplateController::class, 'products'])
            ->name('master-data.import-template.products');
        Route::get('/pdf/product-label/{id}', [PdfController::class, 'productLabel'])->name('pdf.product-label');
    });
    Route::middleware('permission:manage-suppliers')->group(function () {
        Route::get('/suppliers', SupplierList::class)->name('suppliers.index');
        Route::get('/master-data/import-template/suppliers', [MasterDataTemplateController::class, 'suppliers'])
            ->name('master-data.import-template.suppliers');
    });
    Route::middleware('permission:manage-customers')->group(function () {
        Route::get('/customers', CustomerList::class)->name('customers.index');
        Route::get('/master-data/import-template/customers', [MasterDataTemplateController::class, 'customers'])
            ->name('master-data.import-template.customers');
    });
    Route::middleware('permission:manage-warehouses')->group(function () {
        Route::get('/warehouses', WarehouseList::class)->name('warehouses.index');
    });

    // Inventory
    Route::middleware('permission:view-inventory')->group(function () {
        Route::get('/stocks', StockList::class)->name('stocks.index');
        Route::get('/stock-card', StockCard::class)->name('stock-card.index');
    });
    Route::middleware('permission:manage-transfers')->group(function () {
        Route::get('/stock-transfers', StockTransferList::class)->name('stock-transfers.index');
    });
    Route::middleware('permission:manage-opnames')->group(function () {
        Route::get('/stock-opnames', StockOpnameList::class)->name('stock-opnames.index');
    });

    // Purchasing
    Route::middleware('permission:manage-purchases')->group(function () {
        Route::get('/purchase-orders', PurchaseOrderList::class)->name('purchase-orders.index');
        Route::get('/goods-receipts', GoodsReceiptList::class)->name('goods-receipts.index');
        Route::get('/purchase-returns', PurchaseReturnList::class)->name('purchase-returns.index');
    });

    // Sales
    Route::middleware('permission:manage-sales')->group(function () {
        Route::get('/sales-orders', SalesOrderList::class)->name('sales-orders.index');
        Route::get('/sales-returns', SalesReturnList::class)->name('sales-returns.index');
    });
    Route::middleware('permission:use-pos')->group(function () {
        Route::get('/pos', PosTerminal::class)->name('pos.index');
    });
    Route::middleware('permission:manage-logistics')->group(function () {
        Route::get('/shipments', ShipmentList::class)->name('shipments.index');
        Route::get('/pdf/delivery-note/{id}', [PdfController::class, 'shipmentDeliveryNote'])->name('pdf.delivery-note');
    });

    // PDF Routes (accessible to users who can manage sales)
    Route::middleware('permission:manage-sales')->group(function () {
        Route::get('/pdf/pos-receipt/{id}', [PdfController::class, 'posReceipt'])->name('pdf.pos-receipt');
        Route::get('/pdf/invoice/{id}', [PdfController::class, 'salesOrderInvoice'])->name('pdf.invoice');
    });

    // Finance
    Route::middleware('permission:manage-finance')->group(function () {
        Route::get('/chart-of-accounts', ChartOfAccounts::class)->name('chart-of-accounts.index');
        Route::get('/journal-entries', JournalEntryList::class)->name('journal-entries.index');
        Route::get('/cash-transactions', CashTransactionList::class)->name('cash-transactions.index');
        Route::get('/account-payables', AccountPayableList::class)->name('account-payables.index');
        Route::get('/account-receivables', AccountReceivableList::class)->name('account-receivables.index');
        Route::get('/bank-reconciliations', BankReconciliationList::class)->name('bank-reconciliations.index');
    });

    // Reports
    Route::middleware('permission:view-dashboard')->group(function () {
        Route::get('/reports/sales', SalesReport::class)->name('reports.sales');
        Route::get('/reports/profit-loss', ProfitLossReport::class)->name('reports.profit-loss');
        Route::get('/reports/balance-sheet', BalanceSheetReport::class)->name('reports.balance-sheet');
        Route::get('/reports/inventory-turnover', InventoryTurnoverReport::class)->name('reports.inventory-turnover');
        Route::get('/reorder-alerts', ReorderAlertList::class)->name('reorder-alerts.index');
    });

    // HR & Payroll (Fase 4)
    Route::middleware('permission:manage-employees')->group(function () {
        Route::get('/employees', EmployeeList::class)->name('employees.index');
    });
    Route::middleware('permission:manage-payroll')->group(function () {
        Route::get('/payrolls', PayrollList::class)->name('payrolls.index');
    });

    // Integrasi Marketplace (Fase 4)
    Route::middleware('permission:manage-marketplace')->group(function () {
        Route::get('/marketplace-channels', MarketplaceChannelList::class)->name('marketplace-channels.index');
        Route::get('/marketplace-orders', MarketplaceOrderList::class)->name('marketplace-orders.index');
    });

    // Payment Gateway (Fase 4)
    Route::middleware('permission:manage-gateway')->group(function () {
        Route::get('/gateway-transactions', GatewayTransactionList::class)->name('gateway-transactions.index');
    });

    // Manajemen Konsinyasi (Fase 4)
    Route::middleware('permission:manage-consignment')->group(function () {
        Route::get('/consignments', ConsignmentList::class)->name('consignments.index');
        Route::get('/consignment-settlements', ConsignmentSettlementList::class)->name('consignment-settlements.index');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
