<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\StockChanged;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Support\Facades\DB;

class StockTransferService
{
    /**
     * Create a new stock transfer (status: draft).
     *
     * @param  array{source_warehouse_id: int|string, destination_warehouse_id: int|string, notes?: string}  $data
     * @param  list<array{product_id: int|string, quantity: float|int|string}>  $items
     */
    public function createTransfer(array $data, array $items, ?int $userId = null): StockTransfer
    {
        return DB::transaction(function () use ($data, $items, $userId) {
            $transfer = StockTransfer::create([
                'transfer_number' => $this->generateTransferNumber(),
                'source_warehouse_id' => (int) $data['source_warehouse_id'],
                'destination_warehouse_id' => (int) $data['destination_warehouse_id'],
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($items as $item) {
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (float) $item['quantity'],
                    'received_quantity' => 0,
                ]);
            }

            return $transfer->fresh()->load('items');
        });
    }

    /**
     * Approve a transfer (draft/pending_approval only). Stock leaves source warehouse.
     */
    public function approve(StockTransfer $transfer, int $userId): StockTransfer
    {
        if (! in_array($transfer->status, ['draft', 'pending_approval'], true)) {
            throw new \RuntimeException("Transfer tidak dapat disetujui. Status saat ini: {$transfer->status}");
        }

        return DB::transaction(function () use ($transfer, $userId) {
            $transfer->update([
                'status' => 'approved',
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            foreach ($transfer->items as $item) {
                event(new StockChanged(
                    productId: $item->product_id,
                    warehouseId: $transfer->source_warehouse_id,
                    type: 'transfer_out',
                    quantity: (float) $item->quantity,
                    referenceType: 'stock_transfer',
                    referenceId: $transfer->id,
                    userId: $userId,
                    notes: 'Transfer keluar '.$transfer->transfer_number.' menuju gudang tujuan',
                ));
            }

            return $transfer->fresh()->load('items');
        });
    }

    /**
     * Mark transfer as in transit (approved only).
     */
    public function ship(StockTransfer $transfer, int $userId): StockTransfer
    {
        if ($transfer->status !== 'approved') {
            throw new \RuntimeException("Transfer harus berstatus approved sebelum dikirim. Status saat ini: {$transfer->status}");
        }

        $transfer->update(['status' => 'in_transit']);

        return $transfer->fresh()->load('items');
    }

    /**
     * Receive a transfer (approved/in_transit). Stock enters destination warehouse.
     * Supports partial receive via per-item received_quantity.
     *
     * @param  list<array{stock_transfer_item_id: int, quantity: float|int|string}>  $items
     */
    public function receive(StockTransfer $transfer, array $items, int $userId): StockTransfer
    {
        if (! in_array($transfer->status, ['approved', 'in_transit'], true)) {
            throw new \RuntimeException("Transfer tidak dapat diterima. Status saat ini: {$transfer->status}");
        }

        return DB::transaction(function () use ($transfer, $items, $userId) {
            foreach ($items as $entry) {
                $item = StockTransferItem::findOrFail($entry['stock_transfer_item_id']);

                if ($item->stock_transfer_id !== $transfer->id) {
                    throw new \RuntimeException('Item transfer tidak milik transfer ini.');
                }

                $qty = (float) $entry['quantity'];
                $newReceived = (float) $item->received_quantity + $qty;

                if ($newReceived > (float) $item->quantity) {
                    throw new \RuntimeException(
                        "Jumlah terima ({$newReceived}) melebihi jumlah dikirim ({$item->quantity}) untuk produk ID {$item->product_id}"
                    );
                }

                $item->update(['received_quantity' => $newReceived]);

                event(new StockChanged(
                    productId: $item->product_id,
                    warehouseId: $transfer->destination_warehouse_id,
                    type: 'transfer_in',
                    quantity: $qty,
                    referenceType: 'stock_transfer',
                    referenceId: $transfer->id,
                    userId: $userId,
                    notes: 'Penerimaan transfer '.$transfer->transfer_number,
                ));
            }

            $allReceived = $transfer->items()->get()->every(
                fn (StockTransferItem $i) => (float) $i->received_quantity >= (float) $i->quantity
            );

            if ($allReceived) {
                $transfer->update(['status' => 'received']);
            }

            return $transfer->fresh()->load('items');
        });
    }

    /**
     * Cancel a transfer (only before any stock movement happened).
     */
    public function cancel(StockTransfer $transfer): StockTransfer
    {
        if (in_array($transfer->status, ['approved', 'in_transit', 'received'], true)) {
            throw new \RuntimeException('Transfer yang sudah disetujui/dikirim/diterima tidak dapat dibatalkan.');
        }

        $transfer->update(['status' => 'cancelled']);

        return $transfer->fresh()->load('items');
    }

    private function generateTransferNumber(): string
    {
        $prefix = 'TRF-'.now()->format('Ymd').'-';

        $last = StockTransfer::where('transfer_number', 'like', $prefix.'%')
            ->orderByDesc('transfer_number')
            ->value('transfer_number');

        $seq = $last ? ((int) substr((string) $last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
