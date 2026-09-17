<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\MarketplaceChannel;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceProductSync;
use App\Models\MarketplaceSyncLog;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Marketplace integration service (PRD Fase 4 #13).
 *
 * Sinkronisasi dua arah: menarik order dari channel menjadi sales order,
 * dan mendorong stok/harga master ke channel. Karena setiap channel punya
 * API berbeda, pembacaan payload dilakukan oleh "adapter" yang me-rekam
 * hasilnya ke marketplace_sync_logs untuk audit.
 */
class MarketplaceSyncService
{
    public function __construct(private readonly SalesOrderService $salesOrderService) {}

    /**
     * Impor order dari payload channel (hasil adapter API channel).
     *
     * @param  array{channel_order_id?: ?string, channel_order_number?: ?string, customer_name?: ?string, customer_phone?: ?string, shipping_address?: ?string, courier_name?: ?string, tracking_number?: ?string, total_amount?: float, shipping_cost?: float, commission?: float, items?: list<array{product_id: int, quantity: float, unit_price: float}>}  $payload
     *
     * @throws RuntimeException bila channel tidak aktif atau order sudah ada.
     */
    public function importOrder(MarketplaceChannel $channel, array $payload, ?int $userId): MarketplaceOrder
    {
        return DB::transaction(function () use ($channel, $payload): MarketplaceOrder {
            $channel = MarketplaceChannel::whereKey($channel->getKey())->lockForUpdate()->firstOrFail();

            if ($channel->status !== 'active') {
                throw new RuntimeException('Channel '.$channel->name.' tidak aktif — sinkronisasi dilewati.');
            }

            $channelOrderId = (string) ($payload['channel_order_id'] ?? $payload['channel_order_number'] ?? '');

            if ($channelOrderId !== '') {
                $existing = MarketplaceOrder::where('marketplace_channel_id', $channel->id)
                    ->where('channel_order_id', $channelOrderId)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    throw new RuntimeException('Order channel '.$channelOrderId.' sudah pernah diimpor.');
                }
            }

            $order = MarketplaceOrder::create([
                'channel_order_id' => $channelOrderId !== '' ? $channelOrderId : null,
                'channel_order_number' => $payload['channel_order_number'] ?? null,
                'marketplace_channel_id' => $channel->id,
                'customer_name' => $payload['customer_name'] ?? null,
                'customer_phone' => $payload['customer_phone'] ?? null,
                'shipping_address' => $payload['shipping_address'] ?? null,
                'courier_name' => $payload['courier_name'] ?? null,
                'tracking_number' => $payload['tracking_number'] ?? null,
                'total_amount' => (float) ($payload['total_amount'] ?? 0),
                'shipping_cost' => (float) ($payload['shipping_cost'] ?? 0),
                'commission' => (float) ($payload['commission'] ?? 0),
                'payload' => $payload,
                'status' => 'new',
                'synced_at' => now(),
            ]);

            $this->logSync($channel->id, 'in', 'order', 'success', $order->channel_order_number, 'Order diimpor dari channel.');

            return $order;
        });
    }

    /**
     * Konversi order marketplace menjadi sales order (stok turun, AR terbentuk).
     *
     * @param  array{items: list<array{product_id: int, quantity: float, unit_price: float}>}  $payload  Detail item; jika kosong pakai mapping channel.
     *
     * @throws RuntimeException bila order sudah dikonversi atau item kosong.
     */
    public function convertToSalesOrder(MarketplaceOrder $order, array $payload, ?int $userId): SalesOrder
    {
        return DB::transaction(function () use ($order, $payload, $userId): SalesOrder {
            $order = MarketplaceOrder::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($order->sales_order_id !== null) {
                throw new RuntimeException('Order marketplace '.$order->channel_order_number.' sudah dikonversi menjadi sales order.');
            }

            $items = $payload['items'] ?? [];

            if ($items === []) {
                throw new RuntimeException('Konversi membutuhkan minimal satu item.');
            }

            $channel = $order->channel;

            // Buat/matching pelanggan marketplace (nama + telepon).
            $customer = $this->resolveCustomer($order, $userId);

            $subtotal = 0.0;

            $salesOrder = SalesOrder::create([
                'so_number' => $this->generateSoNumber(),
                'customer_id' => $customer->id,
                'warehouse_id' => $channel->warehouse_id,
                'status' => 'draft',
                'order_date' => now()->toDateString(),
                'subtotal' => 0,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => 0,
                'notes' => 'Order marketplace '.$channel->name.' — '.($order->channel_order_number ?? $order->channel_order_id ?? 'tanpa nomor'),
                'created_by' => $userId,
            ]);

            foreach ($items as $item) {
                $quantity = (float) $item['quantity'];
                $unitPrice = (float) $item['unit_price'];

                if ($quantity <= 0) {
                    continue;
                }

                SalesOrderItem::create([
                    'sales_order_id' => $salesOrder->id,
                    'product_id' => (int) $item['product_id'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_amount' => 0,
                    'subtotal' => round($quantity * $unitPrice, 2),
                ]);

                $subtotal += $quantity * $unitPrice;
            }

            $shippingCost = (float) $order->shipping_cost;
            $total = round($subtotal + $shippingCost, 2);

            $salesOrder->update([
                'subtotal' => round($subtotal, 2),
                'total_amount' => $total,
            ]);

            $order->update([
                'sales_order_id' => $salesOrder->id,
                'status' => 'converted',
            ]);

            $this->salesOrderService->confirm($salesOrder, (int) $userId);

            $this->logSync($channel->id, 'out', 'order', 'success', $order->channel_order_number, 'Order dikonversi menjadi SO '.$salesOrder->so_number.'.');

            return $salesOrder->fresh();
        });
    }

    /**
     * Dorong stok + harga master ke channel untuk produk yang di-mapping.
     *
     * @param  ?list<int>  $productIds  Batasi ke produk tertentu; null = semua yang aktif.
     * @return array{synced: int, failed: int}
     */
    public function pushStock(MarketplaceChannel $channel, ?array $productIds = null, ?int $userId = null): array
    {
        if (! $channel->sync_stock) {
            return ['synced' => 0, 'failed' => 0];
        }

        $synced = 0;
        $failed = 0;

        $query = MarketplaceProductSync::with('product')
            ->where('marketplace_channel_id', $channel->id)
            ->whereIn('sync_status', ['pending', 'synced', 'error']);

        if ($productIds !== null) {
            $query->whereIn('product_id', $productIds);
        }

        foreach ($query->get() as $mapping) {
            try {
                $available = $this->availableStock($mapping, $channel);

                $mapping->update([
                    'channel_stock' => $available,
                    'channel_price' => $mapping->product?->selling_price,
                    'last_synced_at' => now(),
                    'sync_status' => 'synced',
                    'last_error' => null,
                ]);

                $synced++;
            } catch (RuntimeException $e) {
                $mapping->update([
                    'sync_status' => 'error',
                    'last_error' => $e->getMessage(),
                ]);

                $failed++;

                $this->logSync($channel->id, 'out', 'stock', 'failed', $mapping->channel_product_id, $e->getMessage());
            }
        }

        $channel->update(['last_synced_at' => now()]);

        return ['synced' => $synced, 'failed' => $failed];
    }

    /**
     * Mapping produk master ↔ produk channel.
     */
    public function mapProduct(MarketplaceChannel $channel, int $productId, ?string $channelProductId, ?float $channelPrice = null): MarketplaceProductSync
    {
        return DB::transaction(function () use ($channel, $productId, $channelProductId, $channelPrice): MarketplaceProductSync {
            return MarketplaceProductSync::updateOrCreate(
                [
                    'marketplace_channel_id' => $channel->id,
                    'product_id' => $productId,
                ],
                [
                    'channel_product_id' => $channelProductId,
                    'channel_price' => $channelPrice,
                    'sync_status' => 'pending',
                ]
            );
        });
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    /**
     * Cari/buat pelanggan untuk order marketplace (match nama+telepon).
     */
    private function resolveCustomer(MarketplaceOrder $order, ?int $userId): Customer
    {
        $name = trim((string) ($order->customer_name ?: 'Pelanggan Marketplace'));
        $phone = trim((string) ($order->customer_phone ?? ''));

        $query = Customer::where('name', $name);

        if ($phone !== '') {
            $query->where('phone', $phone);
        }

        $customer = $query->first();

        if ($customer) {
            return $customer;
        }

        return Customer::create([
            'code' => $this->generateCustomerCode(),
            'name' => $name,
            'phone' => $phone !== '' ? $phone : null,
            'address' => $order->shipping_address,
            'type' => 'general',
            'is_active' => true,
        ]);
    }

    /**
     * Stok tersedia untuk produk di gudang channel (kurangi reserved).
     */
    private function availableStock(MarketplaceProductSync $mapping, MarketplaceChannel $channel): float
    {
        $warehouseId = $channel->warehouse_id;

        if ($warehouseId === null) {
            throw new RuntimeException('Channel tanpa gudang default tidak dapat sinkronisasi stok.');
        }

        $stock = Stock::where('product_id', $mapping->product_id)
            ->where('warehouse_id', $warehouseId)
            ->first();

        return (float) ($stock?->quantity ?? 0);
    }

    private function logSync(int $channelId, string $direction, string $entity, string $status, ?string $reference, string $message): void
    {
        MarketplaceSyncLog::create([
            'marketplace_channel_id' => $channelId,
            'direction' => $direction,
            'entity' => $entity,
            'status' => $status,
            'reference' => $reference,
            'message' => $message,
        ]);
    }

    private function generateSoNumber(): string
    {
        $prefix = 'SO-MP'.now()->format('Ymd').'-';

        return $prefix.$this->nextSequence('sales_orders', 'so_number', $prefix);
    }

    private function generateCustomerCode(): string
    {
        $prefix = 'MP-'.now()->format('Ymd').'-';

        return $prefix.$this->nextSequence('customers', 'code', $prefix);
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
