<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\StockChanged;
use App\Models\Account;
use App\Models\AccountReceivable;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

class SalesOrderService
{
    public function __construct(private readonly JournalService $journalService) {}

    /**
     * Confirm a draft sales order: deduct stock for every item,
     * create the receivable (AR) and post the sales journal.
     */
    public function confirm(SalesOrder $salesOrder, int $userId): SalesOrder
    {
        if ($salesOrder->status !== 'draft') {
            throw new \RuntimeException("Hanya sales order berstatus draft yang dapat dikonfirmasi. Status saat ini: {$salesOrder->status}");
        }

        return DB::transaction(function () use ($salesOrder, $userId) {
            $salesOrder->update(['status' => 'confirmed']);

            foreach ($salesOrder->items()->get() as $item) {
                event(new StockChanged(
                    productId: $item->product_id,
                    warehouseId: $salesOrder->warehouse_id,
                    type: 'out',
                    quantity: (float) $item->quantity,
                    referenceType: 'sales_order',
                    referenceId: $salesOrder->id,
                    userId: $userId,
                    notes: 'Konfirmasi SO '.$salesOrder->so_number,
                ));
            }

            $this->createReceivable($salesOrder, $userId);
            $this->postSalesJournal($salesOrder, $userId);

            return $salesOrder->fresh()->load(['customer', 'warehouse', 'items.product', 'creator']);
        });
    }

    /**
     * Cancel a sales order. Restores stock if the order was already confirmed,
     * and voids/cancels the receivable that was created on confirmation.
     */
    public function cancel(SalesOrder $salesOrder, int $userId): SalesOrder
    {
        if ($salesOrder->status === 'cancelled') {
            throw new \RuntimeException('Sales order sudah dibatalkan.');
        }

        if ($salesOrder->status === 'completed') {
            throw new \RuntimeException('Sales order yang sudah selesai tidak dapat dibatalkan.');
        }

        return DB::transaction(function () use ($salesOrder, $userId) {
            if ($salesOrder->status === 'confirmed') {
                foreach ($salesOrder->items()->get() as $item) {
                    event(new StockChanged(
                        productId: $item->product_id,
                        warehouseId: $salesOrder->warehouse_id,
                        type: 'in',
                        quantity: (float) $item->quantity,
                        referenceType: 'sales_order',
                        referenceId: $salesOrder->id,
                        userId: $userId,
                        notes: 'Pembatalan SO '.$salesOrder->so_number,
                    ));
                }

                $this->cancelReceivable($salesOrder);
            }

            $salesOrder->update(['status' => 'cancelled']);

            return $salesOrder->fresh()->load(['customer', 'warehouse', 'items.product', 'creator']);
        });
    }

    /**
     * Create the receivable for a confirmed sales order (PRD 4.5 AR Must Have).
     */
    private function createReceivable(SalesOrder $salesOrder, int $userId): void
    {
        $existing = AccountReceivable::where('reference_type', 'sales_order')
            ->where('reference_id', $salesOrder->id)
            ->first();

        if ($existing) {
            return;
        }

        $total = (float) $salesOrder->total_amount - (float) $salesOrder->paid_amount;

        if ($total <= 0) {
            return; // fully paid at confirmation — no receivable needed
        }

        $datePrefix = 'AR-'.now()->format('Ymd').'-';

        $latest = AccountReceivable::where('ar_number', 'like', $datePrefix.'%')
            ->orderByDesc('ar_number')
            ->value('ar_number');

        $sequence = $latest !== null ? ((int) substr($latest, -4)) + 1 : 1;

        AccountReceivable::create([
            'ar_number' => $datePrefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'customer_id' => $salesOrder->customer_id,
            'warehouse_id' => $salesOrder->warehouse_id,
            'reference_type' => 'sales_order',
            'reference_id' => $salesOrder->id,
            'total_amount' => $total,
            'paid_amount' => 0,
            'remaining_amount' => $total,
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'open',
            'notes' => 'Piutang otomatis dari SO '.$salesOrder->so_number,
        ]);
    }

    /**
     * Void the receivable tied to a cancelled sales order (only if unpaid).
     */
    private function cancelReceivable(SalesOrder $salesOrder): void
    {
        $receivable = AccountReceivable::where('reference_type', 'sales_order')
            ->where('reference_id', $salesOrder->id)
            ->first();

        if (! $receivable || $receivable->status === 'paid') {
            return;
        }

        $receivable->delete();
    }

    /**
     * Post the automatic sales journal for a confirmed SO (PRD 4.5 double-entry).
     * Dr: Piutang Pelanggan, Cr: Penjualan Kredit; plus COGS lines.
     */
    private function postSalesJournal(SalesOrder $salesOrder, int $userId): void
    {
        $receivableAccount = Account::where('code', '1210')->first(); // Piutang Pelanggan
        $revenueAccount = Account::where('code', '4120')->first();    // Penjualan Kredit
        $inventoryAccount = Account::where('code', '1310')->first();   // Persediaan
        $cogsAccount = Account::where('code', '5100')->first();        // HPP

        if (! $receivableAccount || ! $revenueAccount) {
            return;
        }

        $total = (float) $salesOrder->total_amount - (float) $salesOrder->paid_amount;

        $cogs = 0.0;
        if ($inventoryAccount && $cogsAccount) {
            foreach ($salesOrder->items as $item) {
                $avgUnitCost = (float) DB::table('goods_receipt_items as gri')
                    ->join('goods_receipts as gr', 'gri.goods_receipt_id', '=', 'gr.id')
                    ->join('purchase_order_items as poi', 'gri.purchase_order_item_id', '=', 'poi.id')
                    ->where('gri.product_id', $item->product_id)
                    ->avg('poi.unit_price');

                if ($avgUnitCost <= 0) {
                    $avgUnitCost = (float) DB::table('products')
                        ->where('id', $item->product_id)
                        ->value('purchase_price');
                }

                $cogs += $avgUnitCost * (float) $item->quantity;
            }
        }

        try {
            $this->journalService->createSalesJournal(
                referenceType: 'sales_order',
                referenceId: (int) $salesOrder->id,
                revenueAmount: $total,
                receivableOrCashAccountId: $receivableAccount->id,
                revenueAccountId: $revenueAccount->id,
                cogsAmount: $cogs > 0 ? $cogs : null,
                cogsAccountId: $cogsAccount?->id,
                inventoryAccountId: $inventoryAccount?->id,
                userId: $userId,
                description: 'Jurnal penjualan SO '.$salesOrder->so_number,
            );
        } catch (\RuntimeException) {
            // Skip if unbalanced — integrity guard inside JournalService prevents bad posts.
        }
    }
}
