<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\PurchaseOrderService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.erp', ['title' => 'Purchase Order'])]
class PurchaseOrderList extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    public bool $showForm = false;

    public string $supplier_id = '';

    public string $warehouse_id = '';

    public string $order_date = '';

    public string $expected_delivery_date = '';

    public string $notes = '';

    /** @var list<array{product_id: string, quantity: int|float|string, unit_price: int|float|string}> */
    public array $items = [];

    public bool $showReceiveForm = false;

    public ?int $receivePoId = null;

    /** @var list<array{purchase_order_item_id: int, product_name: string, ordered: float, received: float, quantity: string}> */
    public array $receiveItems = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->reset(['supplier_id', 'warehouse_id', 'expected_delivery_date', 'notes', 'items']);
        $this->order_date = now()->toDateString();
        $this->items = [['product_id' => '', 'quantity' => 1, 'unit_price' => 0]];
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function addItem(): void
    {
        $this->items[] = ['product_id' => '', 'quantity' => 1, 'unit_price' => 0];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function store(): void
    {
        $this->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            app(PurchaseOrderService::class)->createPurchaseOrder(
                [
                    'supplier_id' => (int) $this->supplier_id,
                    'warehouse_id' => (int) $this->warehouse_id,
                    'order_date' => $this->order_date,
                    'expected_delivery_date' => $this->expected_delivery_date ?: null,
                    'notes' => $this->notes ?: null,
                ],
                $this->items,
                auth()->id(),
            );

            session()->flash('success', 'Purchase Order berhasil dibuat (draft).');
            $this->showForm = false;
        } catch (\Throwable $e) {
            session()->flash('error', 'Gagal membuat PO: '.$e->getMessage());
        }
    }

    public function submit(int $id): void
    {
        $po = PurchaseOrder::findOrFail($id);

        try {
            app(PurchaseOrderService::class)->approve($po, (int) auth()->id());
            app(PurchaseOrderService::class)->submitToSupplier($po->fresh());
            session()->flash('success', "PO {$po->po_number} disetujui dan dikirim ke supplier.");
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function approve(int $id): void
    {
        $po = PurchaseOrder::findOrFail($id);

        try {
            app(PurchaseOrderService::class)->approve($po, (int) auth()->id());
            session()->flash('success', "PO {$po->po_number} disetujui.");
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function sendToSupplier(int $id): void
    {
        $po = PurchaseOrder::findOrFail($id);

        try {
            app(PurchaseOrderService::class)->submitToSupplier($po);
            session()->flash('success', "PO {$po->po_number} dikirim ke supplier.");
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function openReceive(int $id): void
    {
        $po = PurchaseOrder::with('items.product')->findOrFail($id);

        if (! in_array($po->status, ['approved', 'sent_to_supplier', 'partial_received'])) {
            session()->flash('error', 'PO belum dapat diterima (harus disetujui/dikirim dulu).');

            return;
        }

        $this->receivePoId = $po->id;
        $this->receiveItems = $po->items
            ->map(fn ($item) => [
                'purchase_order_item_id' => $item->id,
                'product_name' => $item->product?->name ?? "Produk #{$item->product_id}",
                'ordered' => (float) $item->quantity,
                'received' => (float) $item->received_quantity,
                'quantity' => (string) max(0, (float) $item->quantity - (float) $item->received_quantity),
            ])
            ->values()
            ->all();
        $this->resetErrorBag();
        $this->showReceiveForm = true;
    }

    public function submitReceive(): void
    {
        $po = PurchaseOrder::findOrFail($this->receivePoId);

        $payload = [];
        foreach ($this->receiveItems as $ri) {
            if ((float) $ri['quantity'] > 0) {
                $payload[] = [
                    'purchase_order_item_id' => $ri['purchase_order_item_id'],
                    'quantity' => (float) $ri['quantity'],
                ];
            }
        }

        if (empty($payload)) {
            $this->addError('receiveItems', 'Minimal satu item harus diterima.');

            return;
        }

        try {
            $gr = app(PurchaseOrderService::class)->receiveGoods($po, $payload, (int) auth()->id());
            session()->flash('success', "Barang diterima. GRN {$gr->grn_number} dibuat, stok bertambah, hutang terbentuk.");
            $this->showReceiveForm = false;
            $this->receivePoId = null;
        } catch (\Throwable $e) {
            session()->flash('error', 'Gagal menerima barang: '.$e->getMessage());
        }
    }

    public function delete(int $id): void
    {
        $po = PurchaseOrder::findOrFail($id);

        if (! in_array($po->status, ['draft', 'submitted', 'cancelled'])) {
            session()->flash('error', 'PO yang sudah diproses tidak dapat dihapus.');

            return;
        }

        $po->delete();
        session()->flash('success', 'Purchase Order berhasil dihapus (soft delete).');
    }

    public function render()
    {
        $query = PurchaseOrder::with(['supplier', 'items'])
            ->when($this->search, fn ($q) => $q->where('po_number', 'like', "%{$this->search}%"))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('order_date');

        return view('livewire.purchase-order-list', [
            'purchaseOrders' => $query->paginate(15),
            'statuses' => PurchaseOrderStatus::cases(),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name']),
        ]);
    }
}
