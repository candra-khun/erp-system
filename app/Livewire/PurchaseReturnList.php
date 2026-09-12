<?php

namespace App\Livewire;

use App\Models\GoodsReceipt;
use App\Models\PurchaseReturn;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.erp', ['title' => 'Retur Pembelian'])]
class PurchaseReturnList extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public bool $showModal = false;

    // Form state
    public ?int $selectedGoodsReceiptId = null;

    public string $returnDate = '';

    public string $notes = '';

    public array $formItems = [];

    // Loaded reference data
    public array $availableReceipts = [];

    public ?string $supplierName = null;

    public ?string $poNumber = null;

    public function mount(): void
    {
        $this->returnDate = now()->format('Y-m-d');
        $this->loadAvailableReceipts();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    private function loadAvailableReceipts(): void
    {
        $this->availableReceipts = GoodsReceipt::with(['purchaseOrder.supplier'])
            ->whereHas('purchaseOrder')
            ->orderByDesc('receipt_date')
            ->get()
            ->map(fn (GoodsReceipt $gr) => [
                'id' => $gr->id,
                'label' => $gr->grn_number.' - '.$gr->purchaseOrder->po_number.' ('.($gr->purchaseOrder->supplier->name ?? '-').')',
            ])
            ->toArray();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->selectedGoodsReceiptId = null;
        $this->returnDate = now()->format('Y-m-d');
        $this->notes = '';
        $this->formItems = [];
        $this->supplierName = null;
        $this->poNumber = null;
        $this->resetErrorBag();
    }

    public function updatedSelectedGoodsReceiptId(): void
    {
        $this->formItems = [];
        $this->supplierName = null;
        $this->poNumber = null;

        if (! $this->selectedGoodsReceiptId) {
            return;
        }

        $gr = GoodsReceipt::with([
            'purchaseOrder.supplier',
            'items.product',
        ])->find($this->selectedGoodsReceiptId);

        if (! $gr || ! $gr->purchaseOrder) {
            return;
        }

        $this->supplierName = $gr->purchaseOrder->supplier?->name;
        $this->poNumber = $gr->purchaseOrder->po_number;

        foreach ($gr->items as $item) {
            $this->formItems[] = [
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name ?? 'Produk #'.$item->product_id,
                'received_qty' => (float) $item->quantity,
                'quantity' => '',
                'unit_price' => $item->purchaseOrderItem?->unit_price ?? 0,
                'reason' => '',
            ];
        }
    }

    public function store(): void
    {
        $this->validate([
            'selectedGoodsReceiptId' => 'required|exists:goods_receipts,id',
            'returnDate' => 'required|date',
            'notes' => 'nullable|string',
            'formItems' => 'required|array|min:1',
            'formItems.*.quantity' => 'required|numeric|min:0.01',
            'formItems.*.unit_price' => 'required|numeric|min:0',
            'formItems.*.reason' => 'nullable|string',
        ], [
            'selectedGoodsReceiptId.required' => 'Pilih tanda terima barang terlebih dahulu.',
            'formItems.required' => 'Minimal satu item harus diisi.',
            'formItems.*.quantity.required' => 'Qty retur wajib diisi.',
            'formItems.*.quantity.min' => 'Qty retur minimal 0.01.',
        ]);

        DB::transaction(function () {
            $gr = GoodsReceipt::with(['purchaseOrder'])->findOrFail($this->selectedGoodsReceiptId);
            $warehouseId = $gr->warehouse_id;
            $supplierId = $gr->purchaseOrder->supplier_id;
            $purchaseOrderId = $gr->purchase_order_id;

            $returnNumber = $this->generateReturnNumber();

            $totalAmount = collect($this->formItems)->sum(function (array $item): float {
                return (float) $item['quantity'] * (float) $item['unit_price'];
            });

            $purchaseReturn = PurchaseReturn::create([
                'return_number' => $returnNumber,
                'purchase_order_id' => $purchaseOrderId,
                'supplier_id' => $supplierId,
                'return_date' => $this->returnDate,
                'status' => 'draft',
                'total_amount' => $totalAmount,
                'reason' => $this->notes ?: null,
                'created_by' => auth()->id(),
            ]);

            foreach ($this->formItems as $item) {
                $subtotal = (float) $item['quantity'] * (float) $item['unit_price'];

                $purchaseReturn->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $subtotal,
                    'reason' => $item['reason'] ?: null,
                ]);

                if ($warehouseId !== null) {
                    $stock = Stock::where('product_id', $item['product_id'])
                        ->where('warehouse_id', $warehouseId)
                        ->lockForUpdate()
                        ->first();

                    if ($stock !== null) {
                        $stock->decrement('quantity', $item['quantity']);
                    }

                    StockMovement::create([
                        'product_id' => $item['product_id'],
                        'warehouse_id' => $warehouseId,
                        'type' => 'out',
                        'quantity' => $item['quantity'],
                        'reference_type' => PurchaseReturn::class,
                        'reference_id' => $purchaseReturn->id,
                        'notes' => 'Purchase return: '.$returnNumber,
                        'created_by' => auth()->id(),
                        'movement_date' => now(),
                    ]);
                }
            }
        });

        session()->flash('message', 'Retur pembelian berhasil dibuat.');
        $this->closeModal();
    }

    public function approve(int $id): void
    {
        $pr = PurchaseReturn::findOrFail($id);

        if ($pr->status !== 'draft') {
            session()->flash('error', 'Hanya retur berstatus draft yang bisa disetujui.');

            return;
        }

        $hasMovements = StockMovement::where('reference_type', PurchaseReturn::class)
            ->where('reference_id', $pr->id)
            ->where('type', 'out')
            ->exists();

        if (! $hasMovements && $pr->items()->exists()) {
            session()->flash('error', 'Penyesuaian stok harus dicatat sebelum persetujuan.');

            return;
        }

        $pr->update(['status' => 'approved']);
        session()->flash('message', 'Retur pembelian berhasil disetujui.');
    }

    public function cancel(int $id): void
    {
        $pr = PurchaseReturn::findOrFail($id);

        if ($pr->status === 'cancelled') {
            session()->flash('error', 'Retur pembelian sudah dibatalkan.');

            return;
        }

        DB::transaction(function () use ($pr): void {
            $movements = StockMovement::where('reference_type', PurchaseReturn::class)
                ->where('reference_id', $pr->id)
                ->where('type', 'out')
                ->get();

            foreach ($movements as $movement) {
                $stock = Stock::where('product_id', $movement->product_id)
                    ->where('warehouse_id', $movement->warehouse_id)
                    ->lockForUpdate()
                    ->first();

                if ($stock !== null) {
                    $stock->increment('quantity', $movement->quantity);
                }

                StockMovement::create([
                    'product_id' => $movement->product_id,
                    'warehouse_id' => $movement->warehouse_id,
                    'type' => 'in',
                    'quantity' => $movement->quantity,
                    'reference_type' => PurchaseReturn::class,
                    'reference_id' => $pr->id,
                    'notes' => 'Reversal of purchase return: '.$pr->return_number,
                    'created_by' => auth()->id(),
                    'movement_date' => now(),
                ]);
            }

            $pr->update(['status' => 'cancelled']);
        });

        session()->flash('message', 'Retur pembelian berhasil dibatalkan.');
    }

    public function delete(int $id): void
    {
        $pr = PurchaseReturn::findOrFail($id);

        if (! in_array($pr->status, ['draft', 'cancelled'], true)) {
            session()->flash('error', 'Hanya retur draft atau batal yang bisa dihapus.');

            return;
        }

        $pr->delete();
        session()->flash('message', 'Retur pembelian berhasil dihapus.');
    }

    private function generateReturnNumber(): string
    {
        $datePrefix = 'PR-'.now()->format('Ymd').'-';

        $latest = PurchaseReturn::where('return_number', 'like', $datePrefix.'%')
            ->orderByDesc('return_number')
            ->value('return_number');

        $sequence = 1;

        if ($latest !== null) {
            $lastSequence = (int) substr($latest, -4);
            $sequence = $lastSequence + 1;
        }

        return $datePrefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function render(): View
    {
        $query = PurchaseReturn::with(['supplier', 'purchaseOrder', 'creator'])
            ->when($this->search, fn ($q) => $q->where('return_number', 'like', "%{$this->search}%"))
            ->orderByDesc('return_date');

        return view('livewire.purchase-return-list', [
            'returns' => $query->paginate(15),
        ]);
    }
}
