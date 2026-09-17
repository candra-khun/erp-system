<?php

namespace App\Livewire;

use App\Enums\ShipmentStatus;
use App\Livewire\Concerns\InteractsWithWarehouseAccess;
use App\Models\Courier;
use App\Models\SalesOrder;
use App\Models\Shipment;
use App\Services\ShipmentService;
use App\Support\WarehouseAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ShipmentList extends Component
{
    use InteractsWithWarehouseAccess, WithPagination;

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $search = '';

    // Form surat jalan baru
    public bool $showForm = false;

    public ?int $salesOrderId = null;

    public ?int $courierId = null;

    public string $recipientName = '';

    public ?string $recipientPhone = null;

    public ?string $destinationAddress = null;

    public float $totalWeightKg = 0;

    public float $shippingCost = 0;

    public ?string $notes = null;

    // Form kurir
    public bool $showCourierForm = false;

    public ?int $courierEditId = null;

    public string $courierCode = '';

    public string $courierName = '';

    public string $courierType = 'external';

    public ?string $courierPhone = null;

    public float $courierCostPerKg = 0;

    public bool $courierActive = true;

    public string $errorMessage = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    /** @return list<array{value: string, label: string}> */
    #[Computed]
    public function statuses(): array
    {
        return collect(ShipmentStatus::cases())
            ->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])
            ->all();
    }

    /** @return LengthAwarePaginator<Shipment> */
    #[Computed]
    public function shipments()
    {
        return Shipment::with(['salesOrder.warehouse', 'salesOrder.customer', 'courier'])
            ->accessibleWarehouse($this->accessibleWarehouseIds())
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search !== '', function ($q): void {
                $search = '%'.$this->search.'%';
                $q->where(fn ($w) => $w
                    ->where('shipment_number', 'like', $search)
                    ->orWhere('recipient_name', 'like', $search)
                    ->orWhereHas('salesOrder', fn ($so) => $so->where('so_number', 'like', $search)));
            })
            ->orderByDesc('id')
            ->paginate(10);
    }

    /** @return list<SalesOrder> SO yang bisa dibuatkan surat jalan */
    #[Computed]
    public function deliverableOrders()
    {
        return SalesOrder::whereIn('status', ['confirmed', 'processing'])
            ->accessibleWarehouse($this->accessibleWarehouseIds())
            ->with('customer')
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    /** @return LengthAwarePaginator<Courier> */
    #[Computed]
    public function couriers()
    {
        return Courier::orderBy('name')->paginate(10, ['*'], 'courierPage');
    }

    /** @return list<Courier> opsi kurir untuk dropdown form surat jalan */
    #[Computed]
    public function courierOptions(): array
    {
        return Courier::orderBy('name')->get()->all();
    }

    public function openForm(): void
    {
        $this->resetValidation();
        $this->errorMessage = '';
        $this->showForm = true;
    }

    public function saveShipment(ShipmentService $service): void
    {
        $this->validate([
            'salesOrderId' => 'required|exists:sales_orders,id',
            'courierId' => 'nullable|exists:couriers,id',
            'recipientName' => 'nullable|string|max:150',
            'recipientPhone' => 'nullable|string|max:30',
            'destinationAddress' => 'nullable|string|max:500',
            'totalWeightKg' => 'nullable|numeric|min:0',
            'shippingCost' => 'nullable|numeric|min:0',
        ]);

        try {
            $salesOrder = SalesOrder::findOrFail($this->salesOrderId);

            $warehouseId = $salesOrder->warehouse_id;
            if ($warehouseId !== null && ! WarehouseAccess::canAccess(Auth::user(), (int) $warehouseId)) {
                abort(403, 'Anda tidak memiliki akses ke sales order dari cabang ini.');
            }

            $service->createFromSalesOrder(
                $salesOrder,
                [
                    'courier_id' => $this->courierId,
                    'recipient_name' => $this->recipientName,
                    'recipient_phone' => $this->recipientPhone,
                    'destination_address' => $this->destinationAddress,
                    'total_weight_kg' => $this->totalWeightKg,
                    'shipping_cost' => $this->shippingCost,
                    'notes' => $this->notes,
                ],
                (int) Auth::id(),
            );

            $this->showForm = false;
            $this->reset(['salesOrderId', 'courierId', 'recipientName', 'recipientPhone', 'destinationAddress', 'totalWeightKg', 'shippingCost', 'notes']);
            session()->flash('success', 'Surat jalan berhasil dibuat.');
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Ambil shipment dan pastikan user berhak mengakses cabangnya.
     *
     * @throws HttpException 403 bila shipment milik cabang lain.
     */
    private function resolveAccessibleShipment(int $id): Shipment
    {
        $shipment = Shipment::with('salesOrder')->findOrFail($id);
        $warehouseId = $shipment->salesOrder?->warehouse_id;

        if ($warehouseId !== null && ! WarehouseAccess::canAccess(Auth::user(), (int) $warehouseId)) {
            abort(403, 'Anda tidak memiliki akses ke pengiriman dari cabang ini.');
        }

        return $shipment;
    }

    public function printDeliveryNote(int $id): void
    {
        $shipment = $this->resolveAccessibleShipment($id);

        $this->redirectRoute('pdf.delivery-note', ['id' => $shipment->id], navigate: false);
    }

    public function dispatchShipment(int $id, ShipmentService $service): void
    {
        try {
            $service->dispatch($this->resolveAccessibleShipment($id), (int) Auth::id());
            session()->flash('success', 'Surat jalan ditandai dikirim.');
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function markInTransit(int $id, ShipmentService $service): void
    {
        try {
            $service->markInTransit($this->resolveAccessibleShipment($id), (int) Auth::id());
            session()->flash('success', 'Surat jalan dalam perjalanan.');
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function markDelivered(int $id, ShipmentService $service): void
    {
        try {
            $service->markDelivered($this->resolveAccessibleShipment($id), (int) Auth::id());
            session()->flash('success', 'Pengiriman diterima pelanggan. SO selesai.');
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function cancelShipment(int $id, ShipmentService $service): void
    {
        try {
            $service->cancel($this->resolveAccessibleShipment($id));
            session()->flash('success', 'Surat jalan dibatalkan.');
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function saveCourier(): void
    {
        $this->validate([
            'courierCode' => 'required|string|max:20|unique:couriers,code'.($this->courierEditId ? ",{$this->courierEditId}" : ''),
            'courierName' => 'required|string|max:150',
            'courierType' => 'required|in:internal,external',
            'courierPhone' => 'nullable|string|max:30',
            'courierCostPerKg' => 'nullable|numeric|min:0',
        ]);

        Courier::updateOrCreate(
            ['id' => $this->courierEditId],
            [
                'code' => $this->courierCode,
                'name' => $this->courierName,
                'type' => $this->courierType,
                'phone' => $this->courierPhone,
                'cost_per_kg' => $this->courierCostPerKg,
                'is_active' => $this->courierActive,
            ],
        );

        $this->showCourierForm = false;
        $this->reset(['courierEditId', 'courierCode', 'courierName', 'courierType', 'courierPhone', 'courierCostPerKg', 'courierActive']);
        $this->courierActive = true;
        session()->flash('success', 'Kurir disimpan.');
    }

    public function editCourier(int $id): void
    {
        $courier = Courier::findOrFail($id);
        $this->courierEditId = $courier->id;
        $this->courierCode = $courier->code;
        $this->courierName = $courier->name;
        $this->courierType = $courier->type;
        $this->courierPhone = $courier->phone;
        $this->courierCostPerKg = (float) $courier->cost_per_kg;
        $this->courierActive = (bool) $courier->is_active;
        $this->showCourierForm = true;
    }

    public function deleteCourier(int $id): void
    {
        Courier::findOrFail($id)->delete();
        session()->flash('success', 'Kurir dihapus.');
    }

    public function render()
    {
        return view('livewire.shipment-list')
            ->layout('components.layouts.erp', ['title' => 'Surat Jalan & Pengiriman']);
    }
}
