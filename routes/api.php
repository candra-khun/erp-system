<?php

use App\Http\Controllers\Api\AccountPayableController;
use App\Http\Controllers\Api\AccountReceivableController;
use App\Http\Controllers\Api\BankReconciliationController;
use App\Http\Controllers\Api\CashTransactionController;
use App\Http\Controllers\Api\CourierController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GoodsReceiptController;
use App\Http\Controllers\Api\JournalEntryController;
use App\Http\Controllers\Api\LoyaltyController;
use App\Http\Controllers\Api\PosShiftController;
use App\Http\Controllers\Api\ProductCategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductPriceController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\PurchaseReturnController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SalesOrderController;
use App\Http\Controllers\Api\SalesReturnController;
use App\Http\Controllers\Api\SalesTransactionController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\StockOpnameController;
use App\Http\Controllers\Api\StockTransferController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->as('api.')->group(function () {

    // Master Data
    Route::middleware('permission:manage-products')->group(function () {
        Route::apiResource('products', ProductController::class);
        Route::apiResource('product-categories', ProductCategoryController::class);
        Route::apiResource('product-prices', ProductPriceController::class);
    });

    Route::middleware('permission:manage-suppliers')->group(function () {
        Route::apiResource('suppliers', SupplierController::class);
    });

    Route::middleware('permission:manage-customers')->group(function () {
        Route::apiResource('customers', CustomerController::class);
    });

    Route::middleware('permission:manage-warehouses')->group(function () {
        Route::apiResource('warehouses', WarehouseController::class);
    });

    // Inventory
    Route::middleware('permission:view-inventory')->group(function () {
        Route::apiResource('stock-movements', StockMovementController::class)->only(['index', 'show']);
    });

    Route::middleware('permission:manage-transfers')->group(function () {
        Route::apiResource('stock-transfers', StockTransferController::class)->except(['update', 'destroy']);
        Route::post('stock-transfers/{stockTransfer}/approve', [StockTransferController::class, 'approve'])
            ->name('stock-transfers.approve');
        Route::post('stock-transfers/{stockTransfer}/ship', [StockTransferController::class, 'ship'])
            ->name('stock-transfers.ship');
        Route::post('stock-transfers/{stockTransfer}/receive', [StockTransferController::class, 'receive'])
            ->name('stock-transfers.receive');
        Route::post('stock-transfers/{stockTransfer}/cancel', [StockTransferController::class, 'cancel'])
            ->name('stock-transfers.cancel');
    });

    Route::middleware('permission:manage-opnames')->group(function () {
        Route::apiResource('stock-opnames', StockOpnameController::class)->only(['index', 'store', 'show']);
        Route::post('stock-opnames/{stockOpname}/approve', [StockOpnameController::class, 'approve'])
            ->name('stock-opnames.approve');
    });

    // Purchasing
    Route::middleware('permission:manage-purchases')->group(function () {
        Route::apiResource('purchase-orders', PurchaseOrderController::class);
        Route::post('purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])
            ->name('purchase-orders.approve');
        Route::post('purchase-orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submitToSupplier'])
            ->name('purchase-orders.submit');
        Route::apiResource('goods-receipts', GoodsReceiptController::class)->only(['index', 'store', 'show']);
        Route::apiResource('purchase-returns', PurchaseReturnController::class)->except(['update']);
        Route::post('purchase-returns/{purchaseReturn}/approve', [PurchaseReturnController::class, 'approve'])
            ->name('purchase-returns.approve');
        Route::post('purchase-returns/{purchaseReturn}/cancel', [PurchaseReturnController::class, 'cancel'])
            ->name('purchase-returns.cancel');
    });

    // Sales
    Route::middleware('permission:manage-sales')->group(function () {
        Route::apiResource('sales-orders', SalesOrderController::class);
        Route::post('sales-orders/{salesOrder}/confirm', [SalesOrderController::class, 'confirm'])
            ->name('sales-orders.confirm');
        Route::post('sales-orders/{salesOrder}/cancel', [SalesOrderController::class, 'cancel'])
            ->name('sales-orders.cancel');

        // Distribusi & Logistik (Fase 3 — PRD 4.7)
        Route::middleware('permission:manage-logistics')->group(function () {
            Route::apiResource('shipments', ShipmentController::class)->only(['index', 'show', 'store']);
            Route::post('shipments/{shipment}/dispatch', [ShipmentController::class, 'dispatch'])->name('shipments.dispatch');
            Route::post('shipments/{shipment}/in-transit', [ShipmentController::class, 'markInTransit'])->name('shipments.in-transit');
            Route::post('shipments/{shipment}/deliver', [ShipmentController::class, 'markDelivered'])->name('shipments.deliver');
            Route::post('shipments/{shipment}/cancel', [ShipmentController::class, 'cancel'])->name('shipments.cancel');
            Route::apiResource('couriers', CourierController::class);
        });

        // CRM Loyalitas (Fase 3 — PRD 4.6)
        Route::middleware('permission:manage-customers')->group(function () {
            Route::get('customers/{customer}/loyalty', [LoyaltyController::class, 'show'])->name('customers.loyalty.show');
            Route::post('customers/{customer}/loyalty/redeem', [LoyaltyController::class, 'redeem'])->name('customers.loyalty.redeem');
            Route::get('customers/{customer}/communications', [LoyaltyController::class, 'communications'])->name('customers.communications.index');
            Route::post('customers/{customer}/communications', [LoyaltyController::class, 'storeCommunication'])->name('customers.communications.store');
        });
        Route::apiResource('sales-returns', SalesReturnController::class)->only(['index', 'store', 'show']);
        Route::post('sales-returns/{salesReturn}/approve', [SalesReturnController::class, 'approve'])
            ->name('sales-returns.approve');
        Route::post('sales-returns/{salesReturn}/cancel', [SalesReturnController::class, 'cancel'])
            ->name('sales-returns.cancel');
    });

    Route::middleware('permission:use-pos')->group(function () {
        Route::apiResource('sales-transactions', SalesTransactionController::class)->only(['index', 'store', 'show']);
        Route::post('sales-transactions/{salesTransaction}/void', [SalesTransactionController::class, 'void'])
            ->name('sales-transactions.void');
        Route::apiResource('pos-shifts', PosShiftController::class)->only(['index', 'show']);
        Route::post('pos-shifts/open', [PosShiftController::class, 'open'])->name('pos-shifts.open');
        Route::post('pos-shifts/{posShift}/close', [PosShiftController::class, 'close'])->name('pos-shifts.close');
    });

    // Finance
    Route::middleware('permission:manage-finance')->group(function () {
        Route::apiResource('account-payables', AccountPayableController::class);
        Route::post('account-payables/{accountPayable}/pay', [AccountPayableController::class, 'pay'])
            ->name('account-payables.pay');
        Route::apiResource('account-receivables', AccountReceivableController::class);
        Route::post('account-receivables/{accountReceivable}/pay', [AccountReceivableController::class, 'pay'])
            ->name('account-receivables.pay');
        Route::apiResource('cash-transactions', CashTransactionController::class);
        Route::apiResource('journal-entries', JournalEntryController::class);
        Route::post('journal-entries/{journalEntry}/post', [JournalEntryController::class, 'post']);

        // Rekonsiliasi Bank (Fase 3 — PRD 4.5 Could Have)
        Route::get('bank-reconciliations', [BankReconciliationController::class, 'index'])->name('bank-reconciliations.index');
        Route::post('bank-reconciliations', [BankReconciliationController::class, 'store'])->name('bank-reconciliations.store');
        Route::get('bank-reconciliations/{bankReconciliation}', [BankReconciliationController::class, 'show'])->name('bank-reconciliations.show');
        Route::post('bank-reconciliations/{bankReconciliation}/rematch', [BankReconciliationController::class, 'rematch'])->name('bank-reconciliations.rematch');
        Route::post('bank-reconciliations/{bankReconciliation}/complete', [BankReconciliationController::class, 'complete'])->name('bank-reconciliations.complete');
        Route::post('bank-reconciliations/{bankReconciliation}/cancel', [BankReconciliationController::class, 'cancel'])->name('bank-reconciliations.cancel');
        Route::post('bank-statement-lines/{line}/match', [BankReconciliationController::class, 'matchLine'])->name('bank-statement-lines.match');
    });

    // Dashboard & Reports
    Route::middleware('permission:view-dashboard')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
        Route::get('reports/sales-by-product', [ReportController::class, 'salesByProduct'])->name('reports.sales-by-product');
        Route::get('reports/sales-by-warehouse', [ReportController::class, 'salesByWarehouse'])->name('reports.sales-by-warehouse');
        Route::get('reports/sales-by-cashier', [ReportController::class, 'salesByCashier'])->name('reports.sales-by-cashier');
        Route::get('reports/profit-margin', [ReportController::class, 'profitMargin'])->name('reports.profit-margin');
        Route::get('reports/balance-sheet', [ReportController::class, 'balanceSheet'])->name('reports.balance-sheet');
        Route::post('reports/refresh-daily-summary', [ReportController::class, 'refreshDailySummary'])->name('reports.refresh-daily-summary');
    });
});
