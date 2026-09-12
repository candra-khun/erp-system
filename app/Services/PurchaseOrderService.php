<?php

namespace App\Services;

use App\Events\StockChanged;
use App\Models\Account;
use App\Models\AccountPayable;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(
        private JournalService $journalService,
    ) {}

    /**
     * Create a new Purchase Order with items.
     */
    public function createPurchaseOrder(array $data, array $items, ?int $userId = null): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $items, $userId) {
            $po = PurchaseOrder::create([
                'po_number' => $this->generatePoNumber(),
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
                'status' => 'draft',
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $subtotal = 0;
            foreach ($items as $item) {
                $baseQty = $this->convertItemToBaseUnit($item);
                $lineSubtotal = $baseQty * (float) $item['unit_price'];
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $baseQty,
                    'received_quantity' => 0,
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $lineSubtotal,
                ]);
                $subtotal += $lineSubtotal;
            }

            $taxAmount = (float) ($data['tax_amount'] ?? 0);
            $discountAmount = (float) ($data['discount_amount'] ?? 0);
            $po->update([
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'total_amount' => $subtotal + $taxAmount - $discountAmount,
            ]);

            return $po->fresh()->load('items');
        });
    }

    /**
     * Approve a Purchase Order.
     */
    public function approve(PurchaseOrder $po, int $userId): PurchaseOrder
    {
        if (! in_array($po->status, ['draft', 'submitted'])) {
            throw new \RuntimeException("Cannot approve PO with status: {$po->status}");
        }

        $po->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        return $po->fresh();
    }

    /**
     * Submit PO to supplier (after approval).
     */
    public function submitToSupplier(PurchaseOrder $po): PurchaseOrder
    {
        if ($po->status !== 'approved') {
            throw new \RuntimeException('PO must be approved before submitting to supplier.');
        }

        $po->update(['status' => 'sent_to_supplier']);

        return $po->fresh();
    }

    /**
     * Receive goods against a PO. Creates GR, updates stock, creates AP, and posts journal.
     * Supports partial receipt.
     *
     * @param  array<array{purchase_order_item_id: int, quantity: float, batch_number?: string, expiry_date?: string}>  $items
     */
    public function receiveGoods(PurchaseOrder $po, array $items, int $userId, ?string $notes = null): GoodsReceipt
    {
        if (! in_array($po->status, ['approved', 'sent_to_supplier', 'partial_received'])) {
            throw new \RuntimeException("Cannot receive goods for PO with status: {$po->status}");
        }

        return DB::transaction(function () use ($po, $items, $userId, $notes) {
            $gr = GoodsReceipt::create([
                'grn_number' => $this->generateGrnNumber(),
                'purchase_order_id' => $po->id,
                'warehouse_id' => $po->warehouse_id,
                'receipt_date' => now()->toDateString(),
                'notes' => $notes,
                'received_by' => $userId,
            ]);

            $totalReceivedAmount = 0;

            foreach ($items as $item) {
                $poItem = PurchaseOrderItem::findOrFail($item['purchase_order_item_id']);

                if ($poItem->purchase_order_id !== $po->id) {
                    throw new \RuntimeException('PO item does not belong to this PO.');
                }

                $receiveQty = $this->convertItemToBaseUnit(
                    array_merge($item, ['product_id' => $poItem->product_id])
                );
                $newReceivedQty = (float) $poItem->received_quantity + $receiveQty;

                if ($newReceivedQty > (float) $poItem->quantity) {
                    throw new \RuntimeException(
                        "Receive quantity ({$newReceivedQty}) exceeds ordered quantity ({$poItem->quantity}) for product ID {$poItem->product_id}"
                    );
                }

                GoodsReceiptItem::create([
                    'goods_receipt_id' => $gr->id,
                    'purchase_order_item_id' => $poItem->id,
                    'product_id' => $poItem->product_id,
                    'quantity' => $receiveQty,
                    'batch_number' => $item['batch_number'] ?? null,
                    'expiry_date' => $item['expiry_date'] ?? null,
                ]);

                $poItem->update(['received_quantity' => $newReceivedQty]);

                $lineAmount = $receiveQty * (float) $poItem->unit_price;
                $totalReceivedAmount += $lineAmount;

                event(new StockChanged(
                    productId: $poItem->product_id,
                    warehouseId: $po->warehouse_id,
                    type: 'in',
                    quantity: $receiveQty,
                    referenceType: 'goods_receipt',
                    referenceId: $gr->id,
                    userId: $userId,
                    notes: "Penerimaan barang GR {$gr->grn_number}",
                ));
            }

            $allItems = $po->items()->get();
            $allFullyReceived = $allItems->every(fn ($i) => (float) $i->received_quantity >= (float) $i->quantity);
            $anyReceived = $allItems->some(fn ($i) => (float) $i->received_quantity > 0);

            if ($allFullyReceived) {
                $po->update(['status' => 'received']);
            } elseif ($anyReceived) {
                $po->update(['status' => 'partial_received']);
            }

            AccountPayable::create([
                'ap_number' => $this->generateApNumber(),
                'supplier_id' => $po->supplier_id,
                'warehouse_id' => $po->warehouse_id,
                'reference_type' => 'goods_receipt',
                'reference_id' => $gr->id,
                'total_amount' => $totalReceivedAmount,
                'paid_amount' => 0,
                'remaining_amount' => $totalReceivedAmount,
                'due_date' => now()->addDays(30),
                'status' => 'open',
            ]);

            $inventoryAccount = Account::where('code', '1310')->first();
            $payableAccount = Account::where('code', '2110')->first();

            if ($inventoryAccount && $payableAccount) {
                $this->journalService->createGoodsReceiptJournal(
                    goodsReceiptId: $gr->id,
                    totalAmount: $totalReceivedAmount,
                    inventoryAccountId: $inventoryAccount->id,
                    payableAccountId: $payableAccount->id,
                    userId: $userId,
                );
            }

            return $gr->fresh()->load('items');
        });
    }

    /**
     * Create a Purchase Return.
     */
    public function createPurchaseReturn(int $purchaseOrderId, array $items, int $userId, ?string $reason = null): PurchaseReturn
    {
        return DB::transaction(function () use ($purchaseOrderId, $items, $userId, $reason) {
            $po = PurchaseOrder::findOrFail($purchaseOrderId);

            $pr = PurchaseReturn::create([
                'return_number' => $this->generatePrNumber(),
                'purchase_order_id' => $po->id,
                'supplier_id' => $po->supplier_id,
                'return_date' => now()->toDateString(),
                'status' => 'draft',
                'total_amount' => 0,
                'reason' => $reason,
                'created_by' => $userId,
            ]);

            $totalAmount = 0;
            foreach ($items as $item) {
                $subtotal = (float) $item['quantity'] * (float) $item['unit_price'];
                PurchaseReturnItem::create([
                    'purchase_return_id' => $pr->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $subtotal,
                    'reason' => $item['reason'] ?? null,
                ]);
                $totalAmount += $subtotal;
            }

            $pr->update(['total_amount' => $totalAmount]);

            return $pr->fresh()->load('items');
        });
    }

    /**
     * Approve and process a Purchase Return.
     */
    public function approvePurchaseReturn(PurchaseReturn $pr, int $userId): PurchaseReturn
    {
        if ($pr->status !== 'draft') {
            throw new \RuntimeException("Cannot approve PR with status: {$pr->status}");
        }

        return DB::transaction(function () use ($pr, $userId) {
            $pr->update(['status' => 'approved']);

            foreach ($pr->items as $item) {
                event(new StockChanged(
                    productId: $item->product_id,
                    warehouseId: $pr->purchaseOrder->warehouse_id,
                    type: 'out',
                    quantity: (float) $item->quantity,
                    referenceType: 'purchase_return',
                    referenceId: $pr->id,
                    userId: $userId,
                    notes: "Retur pembelian PR {$pr->return_number}",
                ));
            }

            $payableAccount = Account::where('code', '2110')->first();
            $inventoryAccount = Account::where('code', '1310')->first();

            if ($payableAccount && $inventoryAccount) {
                $this->journalService->createPurchaseReturnJournal(
                    purchaseReturnId: $pr->id,
                    totalAmount: (float) $pr->total_amount,
                    payableAccountId: $payableAccount->id,
                    inventoryAccountId: $inventoryAccount->id,
                    userId: $userId,
                );
            }

            $pr->update(['status' => 'processed']);

            return $pr->fresh()->load('items');
        });
    }

    /**
     * Convert item quantity to base unit if unit_id is provided.
     *
     * @param  array{product_id: int, quantity: float, unit_id?: int}  $item
     */
    private function convertItemToBaseUnit(array $item): float
    {
        $quantity = (float) $item['quantity'];

        if (empty($item['unit_id'])) {
            return $quantity;
        }

        $unit = ProductUnit::findOrFail($item['unit_id']);

        if ($unit->is_base) {
            return $quantity;
        }

        $product = Product::findOrFail($item['product_id']);

        return $product->convertToBaseUnit($quantity, $unit);
    }

    private function generatePoNumber(): string
    {
        $date = now()->format('Ymd');
        $last = PurchaseOrder::whereDate('created_at', today())
            ->orderByDesc('id')
            ->value('po_number');

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last);
            $lastSeq = (int) end($parts);
            $seq = $lastSeq + 1;
        }

        return 'PO-'.$date.'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function generateGrnNumber(): string
    {
        $date = now()->format('Ymd');
        $last = GoodsReceipt::whereDate('created_at', today())
            ->orderByDesc('id')
            ->value('grn_number');

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last);
            $lastSeq = (int) end($parts);
            $seq = $lastSeq + 1;
        }

        return 'GRN-'.$date.'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function generateApNumber(): string
    {
        $date = now()->format('Ymd');
        $last = AccountPayable::whereDate('created_at', today())
            ->orderByDesc('id')
            ->value('ap_number');

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last);
            $lastSeq = (int) end($parts);
            $seq = $lastSeq + 1;
        }

        return 'AP-'.$date.'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function generatePrNumber(): string
    {
        $date = now()->format('Ymd');
        $last = PurchaseReturn::whereDate('created_at', today())
            ->orderByDesc('id')
            ->value('return_number');

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last);
            $lastSeq = (int) end($parts);
            $seq = $lastSeq + 1;
        }

        return 'PR-'.$date.'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
