<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Stock;
use App\Models\User;
use App\Notifications\LowStockAlertNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class CheckReorderAlertsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Kirim notifikasi stok rendah ke user yang berwenang melakukan pembelian.
     * Anti-duplikasi per produk+gudang selama 24 jam via cache lock.
     */
    public function handle(): void
    {
        $alerts = Stock::query()
            ->join('products', 'stocks.product_id', '=', 'products.id')
            ->join('warehouses', 'stocks.warehouse_id', '=', 'warehouses.id')
            ->whereColumn('stocks.quantity', '<=', 'products.reorder_point')
            ->where('products.is_active', true)
            ->where('products.reorder_alert_enabled', true)
            ->whereNotNull('products.reorder_point')
            ->where('products.reorder_point', '>', 0)
            ->whereNull('products.deleted_at')
            ->whereNull('warehouses.deleted_at')
            ->select([
                'stocks.product_id',
                'stocks.warehouse_id',
                'stocks.quantity',
                'products.sku',
                'products.name',
                'products.reorder_point',
                'warehouses.name as warehouse_name',
            ])
            ->get();

        if ($alerts->isEmpty()) {
            return;
        }

        $recipients = User::query()
            ->whereHas('roles.permissions', fn ($query) => $query->where('name', 'manage-purchases'))
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        foreach ($alerts as $row) {
            $lockKey = "reorder-alert:{$row->product_id}:{$row->warehouse_id}";

            if (! Cache::add($lockKey, true, now()->addDay())) {
                continue;
            }

            $quantity = (float) $row->quantity;
            $reorderPoint = (float) $row->reorder_point;

            Notification::send($recipients, new LowStockAlertNotification([
                'product_id' => (int) $row->product_id,
                'sku' => (string) $row->sku,
                'name' => (string) $row->name,
                'warehouse_id' => (int) $row->warehouse_id,
                'warehouse' => (string) $row->warehouse_name,
                'quantity' => $quantity,
                'reorder_point' => $reorderPoint,
                'deficit' => round($reorderPoint - $quantity, 2),
            ]));
        }

        Log::info('Reorder alert job selesai', [
            'alerts' => $alerts->count(),
            'recipients' => $recipients->count(),
        ]);
    }
}
