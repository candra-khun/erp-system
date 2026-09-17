<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ConsignmentItemStatus;
use App\Enums\ConsignmentSettlementStatus;
use App\Models\Account;
use App\Models\AccountPayable;
use App\Models\ConsignmentIn;
use App\Models\ConsignmentItem;
use App\Models\ConsignmentSettlement;
use App\Models\ConsignmentSettlementItem;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Consignment management service (PRD Fase 4 #15).
 *
 * Barang titipan supplier: terima (stok masuk tanpa hutang) → jual
 * (stok berkurang, qty_sold bertambah) → settlement (hitung yang terjual,
 * buat AP ke supplier + jurnal Dr Persediaan / Cr Hutang Supplier).
 */
class ConsignmentService
{
    public function __construct(private readonly JournalService $journalService) {}

    /**
     * Terima barang konsinyasi: catat consignment_in + items + stok gudang.
     *
     * @param  array<int, array{product_id: int, quantity: float, consignment_price: float, selling_price: float}>  $items
     *
     * @throws RuntimeException bila produk tidak ditemukan atau qty invalid.
     */
    public function receiveConsignment(int $supplierId, ?int $warehouseId, array $items, ?int $userId, ?string $notes = null): ConsignmentIn
    {
        if ($items === []) {
            throw new RuntimeException('Penerimaan konsinyasi harus memiliki minimal satu item.');
        }

        return DB::transaction(function () use ($supplierId, $warehouseId, $items, $userId, $notes): ConsignmentIn {
            $consignment = ConsignmentIn::create([
                'consignment_number' => $this->generateConsignmentNumber(),
                'supplier_id' => $supplierId,
                'warehouse_id' => $warehouseId,
                'received_date' => now()->toDateString(),
                'status' => 'open',
                'notes' => $notes,
                'created_by' => $userId,
            ]);

            foreach ($items as $item) {
                $product = Product::findOrFail((int) $item['product_id']);
                $quantity = (float) $item['quantity'];

                if ($quantity <= 0) {
                    throw new RuntimeException('Kuantitas produk "'.$product->name.'" harus lebih dari 0.');
                }

                ConsignmentItem::create([
                    'consignment_in_id' => $consignment->id,
                    'product_id' => $product->id,
                    'quantity_received' => $quantity,
                    'quantity_available' => $quantity,
                    'consignment_price' => (float) $item['consignment_price'],
                    'selling_price' => (float) ($item['selling_price'] ?? $product->selling_price),
                    'status' => 'active',
                ]);

                // Stok gudang naik (barang fisik ada, tapi belum punya cost yang diakui).
                $this->adjustStock((int) $product->id, $warehouseId, $quantity, 'consignment_in', (int) $consignment->id, 'Penerimaan konsinyasi '.$consignment->consignment_number);
            }

            return $consignment->fresh()->load('items.product');
        });
    }

    /**
     * Catat penjualan barang konsinyasi: stok turun + qty_sold naik.
     *
     * Dipanggil oleh SalesService/PosService saat item konsinyasi terjual.
     *
     * @throws RuntimeException bila stok konsinyasi tidak mencukupi.
     */
    public function recordSale(int $productId, int $warehouseId, float $quantity, ?int $userId = null): void
    {
        if ($quantity <= 0) {
            return;
        }

        DB::transaction(function () use ($productId, $warehouseId, $quantity): void {
            $item = $this->availableItem($productId, $warehouseId);

            if (! $item) {
                // Bukan barang konsinyasi — abaikan, stok reguler yang menangani.
                return;
            }

            $remaining = (float) $item->quantity_available;

            if ($quantity > $remaining + 0.001) {
                throw new RuntimeException('Stok konsinyasi '.$item->product?->name.' tidak mencukupi (sisa '.$remaining.').');
            }

            $item->increment('quantity_sold', $quantity);
            $item->recalculateAvailable();
            $item = $item->fresh();

            if ((float) $item->quantity_available <= 0.001) {
                $item->update(['status' => ConsignmentItemStatus::SoldOut]);
            }

            $this->adjustStock($productId, $warehouseId, -$quantity, 'consignment_sale', (int) $item->consignment_in_id, 'Penjualan barang konsinyasi');
        });
    }

    /**
     * Kembalikan barang konsinyasi ke supplier (belum terjual).
     *
     * @throws RuntimeException bila kuantitas melebihi yang tersedia.
     */
    public function returnConsignment(ConsignmentItem $item, float $quantity, ?int $userId = null): ConsignmentItem
    {
        return DB::transaction(function () use ($item, $quantity): ConsignmentItem {
            $item = ConsignmentItem::whereKey($item->getKey())->lockForUpdate()->firstOrFail();

            if ($quantity <= 0) {
                throw new RuntimeException('Kuantitas pengembalian harus lebih dari 0.');
            }

            if ($quantity > (float) $item->quantity_available + 0.001) {
                throw new RuntimeException('Kuantitas pengembalian melebihi stok yang tersedia.');
            }

            $item->increment('quantity_returned', $quantity);
            $item->recalculateAvailable();
            $item = $item->fresh();

            $this->adjustStock(
                (int) $item->product_id,
                (int) $item->consignmentIn->warehouse_id,
                -$quantity,
                'consignment_return',
                (int) $item->consignment_in_id,
                'Pengembalian barang konsinyasi'
            );

            if ((float) $item->quantity_available <= 0.001) {
                $item->update(['status' => 'returned']);
            }

            return $item->fresh();
        });
    }

    /**
     * Buat settlement: hitung semua barang konsinyasi yang terjual dari
     * supplier ini, lalu buat Account Payable + jurnal.
     *
     * @param  array{commission?: float, notes?: ?string}  $options
     *
     * @throws RuntimeException bila tidak ada penjualan yang belum disettle.
     */
    public function createSettlement(int $supplierId, ?int $warehouseId, ?int $userId, array $options = []): ConsignmentSettlement
    {
        return DB::transaction(function () use ($supplierId, $warehouseId, $userId, $options): ConsignmentSettlement {
            $items = ConsignmentItem::with('consignmentIn')
                ->whereHas('consignmentIn', fn ($consignment) => $consignment
                    ->where('supplier_id', $supplierId)
                    ->where('status', 'open')
                    ->when($warehouseId !== null, fn ($q) => $q->where('warehouse_id', $warehouseId))
                )
                ->where('quantity_sold', '>', 0)
                ->where('status', '!=', 'settled')
                ->orderBy('product_id')
                ->get();

            if ($items->isEmpty()) {
                throw new RuntimeException('Tidak ada penjualan konsinyasi yang belum disettle untuk supplier ini.');
            }

            $commission = (float) ($options['commission'] ?? 0);

            $settlement = ConsignmentSettlement::create([
                'settlement_number' => $this->generateSettlementNumber(),
                'supplier_id' => $supplierId,
                'warehouse_id' => $warehouseId,
                'period_start' => $items->min(fn ($item) => $item->consignmentIn->received_date),
                'period_end' => now()->toDateString(),
                'total_quantity_sold' => round($items->sum('quantity_sold'), 2),
                'total_amount' => 0,
                'commission_amount' => $commission,
                'status' => 'draft',
                'notes' => $options['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $total = 0.0;

            foreach ($items as $item) {
                $amount = round((float) $item->quantity_sold * (float) $item->consignment_price, 2);
                $total += $amount;

                ConsignmentSettlementItem::create([
                    'consignment_settlement_id' => $settlement->id,
                    'consignment_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity_sold' => $item->quantity_sold,
                    'consignment_price' => $item->consignment_price,
                    'amount' => $amount,
                ]);
            }

            $settlement->update(['total_amount' => round($total, 2)]);

            return $settlement->fresh()->load('items.product');
        });
    }

    /**
     * Konfirmasi settlement: buat AP ke supplier + jurnal, dan tandai item settled.
     *
     * @throws RuntimeException bila settlement sudah dikonfirmasi.
     */
    public function confirmSettlement(ConsignmentSettlement $settlement, ?int $userId): ConsignmentSettlement
    {
        return DB::transaction(function () use ($settlement, $userId): ConsignmentSettlement {
            $settlement = ConsignmentSettlement::whereKey($settlement->getKey())->lockForUpdate()->firstOrFail();

            if ($settlement->status !== ConsignmentSettlementStatus::Draft) {
                throw new RuntimeException('Settlement '.$settlement->settlement_number.' sudah dikonfirmasi sebelumnya.');
            }

            $payable = AccountPayable::create([
                'ap_number' => $this->generateApNumber(),
                'supplier_id' => $settlement->supplier_id,
                'warehouse_id' => $settlement->warehouse_id,
                'total_amount' => (float) $settlement->total_amount - (float) $settlement->commission_amount,
                'paid_amount' => 0,
                'remaining_amount' => (float) $settlement->total_amount - (float) $settlement->commission_amount,
                'due_date' => now()->addDays(30)->toDateString(),
                'status' => 'open',
                'reference_type' => 'consignment_settlement',
                'reference_id' => $settlement->id,
            ]);

            $settlement->update([
                'status' => ConsignmentSettlementStatus::Confirmed,
                'account_payable_id' => $payable->id,
            ]);

            $settlement->items()->each(function (ConsignmentSettlementItem $line): void {
                $line->consignmentItem?->update(['status' => ConsignmentItemStatus::Settled]);
            });

            // Jurnal: Dr Persediaan (1310) / Cr Hutang Supplier (2110).
            $this->postSettlementJournal($settlement, $payable, $userId);

            return $settlement->fresh();
        });
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    /**
     * Item konsinyasi aktif untuk produk + gudang (FIFO berdasarkan penerimaan).
     */
    private function availableItem(int $productId, ?int $warehouseId): ?ConsignmentItem
    {
        return ConsignmentItem::with('consignmentIn', 'product')
            ->where('product_id', $productId)
            ->where('status', 'active')
            ->where('quantity_available', '>', 0)
            ->whereHas('consignmentIn', fn ($consignment) => $consignment
                ->where('status', 'open')
                ->when($warehouseId !== null, fn ($q) => $q->where('warehouse_id', $warehouseId))
            )
            ->orderBy('id') // FIFO
            ->lockForUpdate()
            ->first();
    }

    /**
     * Ubah stok gudang + catat pergerakan stok.
     */
    private function adjustStock(int $productId, ?int $warehouseId, float $quantity, string $type, int $referenceId, string $description): void
    {
        $stock = Stock::firstOrCreate(
            [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
            ],
            ['quantity' => 0]
        );

        $stock->increment('quantity', $quantity);

        // Log pergerakan stok (kolom type adalah enum in/out/adjustment).
        StockMovement::create([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'type' => $quantity >= 0 ? 'in' : 'out',
            'quantity' => abs($quantity),
            'reference_type' => 'consignment',
            'reference_id' => $referenceId,
            'notes' => $description,
            'created_by' => auth()->id(),
            'movement_date' => now(),
        ]);
    }

    /**
     * Jurnal settlement: Dr Persediaan / Cr Hutang Supplier.
     */
    private function postSettlementJournal(ConsignmentSettlement $settlement, AccountPayable $payable, ?int $userId): void
    {
        $debit = Account::where('code', '1310')->first();  // Persediaan
        $credit = Account::where('code', '2110')->first(); // Hutang Supplier

        if (! $debit || ! $credit) {
            return; // CoA belum di-seed — AP tetap tercatat, jurnal dilewati
        }

        $amount = (float) $payable->total_amount;

        if ($amount <= 0) {
            return;
        }

        $description = 'Jurnal konsinyasi '.$settlement->settlement_number;

        $this->journalService->createJournal([
            'journal_date' => now()->toDateString(),
            'type' => 'purchase',
            'reference_type' => 'consignment_settlement',
            'reference_id' => (int) $settlement->id,
            'description' => $description,
            'created_by' => $userId,
            'lines' => [
                ['account_id' => $debit->id, 'type' => 'debit', 'amount' => $amount, 'description' => $description],
                ['account_id' => $credit->id, 'type' => 'credit', 'amount' => $amount, 'description' => $description],
            ],
        ]);
    }

    private function generateConsignmentNumber(): string
    {
        $prefix = 'CG-'.now()->format('Ymd').'-';

        return $prefix.$this->nextSequence('consignment_ins', 'consignment_number', $prefix);
    }

    private function generateSettlementNumber(): string
    {
        $prefix = 'CS-'.now()->format('Ymd').'-';

        return $prefix.$this->nextSequence('consignment_settlements', 'settlement_number', $prefix);
    }

    private function generateApNumber(): string
    {
        $prefix = 'AP-'.now()->format('Ymd').'-';

        return $prefix.$this->nextSequence('account_payables', 'ap_number', $prefix);
    }

    /**
     * Nomor urut berikutnya untuk sebuah kolom dengan prefix tanggal.
     */
    private function nextSequence(string $table, string $column, string $prefix): string
    {
        $latest = DB::table($table)
            ->where($column, 'like', $prefix.'%')
            ->orderByDesc($column)
            ->value($column);

        $sequence = 1;

        if ($latest !== null) {
            $sequence = (int) substr($latest, -4) + 1;
        }

        return str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
