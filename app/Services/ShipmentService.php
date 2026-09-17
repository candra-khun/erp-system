<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Models\Courier;
use App\Models\SalesOrder;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;

/**
 * Distribution & logistics service (PRD 4.7).
 * Handles surat jalan creation from a confirmed sales order and the
 * shipment status flow: preparing -> dispatched -> in_transit -> delivered.
 */
class ShipmentService
{
    /**
     * Create a shipment (surat jalan) for a confirmed/deliverable sales order.
     *
     * @param  array{courier_id?: int, recipient_name?: string, recipient_phone?: string, destination_address?: string, total_weight_kg?: float, shipping_cost?: float, notes?: string}  $data
     *
     * @throws \RuntimeException when the SO is not in a deliverable status or already shipped
     */
    public function createFromSalesOrder(SalesOrder $salesOrder, array $data, int $userId): Shipment
    {
        if (! in_array($salesOrder->status, ['confirmed', 'processing', 'shipped'], true)) {
            throw new \RuntimeException(
                "Sales order berstatus {$salesOrder->status} tidak dapat dikirim. Konfirmasi SO terlebih dahulu."
            );
        }

        $existing = Shipment::where('sales_order_id', $salesOrder->id)
            ->whereNotIn('status', ['cancelled'])
            ->first();

        if ($existing) {
            throw new \RuntimeException('Surat jalan untuk SO ini sudah dibuat: '.$existing->shipment_number);
        }

        return DB::transaction(function () use ($salesOrder, $data, $userId): Shipment {
            $courier = isset($data['courier_id']) && $data['courier_id']
                ? Courier::findOrFail((int) $data['courier_id'])
                : null;

            $shippingCost = (float) ($data['shipping_cost'] ?? 0);
            if ($shippingCost <= 0 && $courier) {
                $shippingCost = (float) $courier->cost_per_kg * (float) ($data['total_weight_kg'] ?? 0);
            }

            $shipment = Shipment::create([
                'shipment_number' => $this->generateShipmentNumber(),
                'sales_order_id' => $salesOrder->id,
                'courier_id' => $courier?->id,
                'recipient_name' => $data['recipient_name'] ?? $salesOrder->customer?->name ?? '',
                'recipient_phone' => $data['recipient_phone'] ?? $salesOrder->customer?->phone ?? null,
                'destination_address' => $data['destination_address'] ?? $salesOrder->customer?->address ?? null,
                'total_weight_kg' => (float) ($data['total_weight_kg'] ?? 0),
                'shipping_cost' => $shippingCost,
                'status' => ShipmentStatus::Preparing,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            // SO bergerak ke processing saat surat jalan dibuat
            if ($salesOrder->status === 'confirmed') {
                $salesOrder->update(['status' => 'processing']);
            }

            return $shipment;
        });
    }

    /**
     * Mark a shipment dispatched (dikirim ke kurir).
     *
     * @throws \RuntimeException when the shipment is not in a dispatchable status
     */
    public function dispatch(Shipment $shipment, int $userId): Shipment
    {
        if ($shipment->status !== ShipmentStatus::Preparing) {
            throw new \RuntimeException('Hanya surat jalan berstatus diproses yang dapat dikirim.');
        }

        return $this->transition($shipment, ShipmentStatus::Dispatched, $userId);
    }

    /**
     * Mark a shipment in transit.
     */
    public function markInTransit(Shipment $shipment, int $userId): Shipment
    {
        if ($shipment->status !== ShipmentStatus::Dispatched) {
            throw new \RuntimeException('Surat jalan harus dikirim terlebih dahulu.');
        }

        return $this->transition($shipment, ShipmentStatus::InTransit, $userId);
    }

    /**
     * Mark a shipment delivered — also completes the sales order.
     */
    public function markDelivered(Shipment $shipment, int $userId): Shipment
    {
        if (! in_array($shipment->status, [ShipmentStatus::Dispatched, ShipmentStatus::InTransit], true)) {
            throw new \RuntimeException('Surat jalan belum dikirim.');
        }

        return DB::transaction(function () use ($shipment): Shipment {
            $shipment->update([
                'status' => ShipmentStatus::Delivered,
                'delivered_at' => now(),
            ]);

            $so = $shipment->salesOrder;
            if ($so && in_array($so->status, ['processing', 'shipped'], true)) {
                $so->update(['status' => 'delivered']);
            }

            return $shipment->fresh()->load(['salesOrder.customer', 'courier']);
        });
    }

    /**
     * Cancel a shipment (only before dispatch).
     */
    public function cancel(Shipment $shipment): Shipment
    {
        if ($shipment->status !== ShipmentStatus::Preparing) {
            throw new \RuntimeException('Surat jalan yang sudah dikirim tidak dapat dibatalkan.');
        }

        $shipment->update(['status' => ShipmentStatus::Cancelled]);

        return $shipment->fresh();
    }

    /**
     * @return Shipment fresh instance after transition
     */
    private function transition(Shipment $shipment, ShipmentStatus $to, int $userId): Shipment
    {
        DB::transaction(function () use ($shipment, $to): void {
            $shipment->update([
                'status' => $to,
                'dispatched_at' => $to === ShipmentStatus::Dispatched ? now() : $shipment->dispatched_at,
            ]);
        });

        return $shipment->fresh()->load(['salesOrder.customer', 'courier']);
    }

    private function generateShipmentNumber(): string
    {
        $datePrefix = 'SJ-'.now()->format('Ymd').'-';

        $latest = Shipment::where('shipment_number', 'like', $datePrefix.'%')
            ->orderByDesc('shipment_number')
            ->value('shipment_number');

        $sequence = $latest !== null ? ((int) substr($latest, -4)) + 1 : 1;

        return $datePrefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
