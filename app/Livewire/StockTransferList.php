<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\StockTransferService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.erp', ['title' => 'Transfer Stok'])]
class StockTransferList extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public bool $showForm = false;

    public string $source_warehouse_id = '';

    public string $destination_warehouse_id = '';

    public string $notes = '';

    /** @var list<array{product_id: string, quantity: float|int|string}> */
    public array $items = [];

    /** @var list<array{stock_transfer_item_id: int, quantity: float|int|string}> */
    public array $receiveItems = [];

    public bool $showReceiveForm = false;

    public ?int $receiveTransferId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->reset(['source_warehouse_id', 'destination_warehouse_id', 'notes', 'items']);
        $this->resetErrorBag();
        $this->items = [['product_id' => '', 'quantity' => 1]];
        $this->showForm = true;
    }

    public function addItem(): void
    {
        $this->items[] = ['product_id' => '', 'quantity' => 1];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function store(): void
    {
        $this->validate([
            'source_warehouse_id' => 'required|exists:warehouses,id',
            'destination_warehouse_id' => 'required|exists:warehouses,id|different:source_warehouse_id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        try {
            app(StockTransferService::class)->createTransfer(
                [
                    'source_warehouse_id' => $this->source_warehouse_id,
                    'destination_warehouse_id' => $this->destination_warehouse_id,
                    'notes' => $this->notes,
                ],
                $this->items,
                auth()->id(),
            );

            session()->flash('success', 'Transfer stok berhasil dibuat.');
            $this->showForm = false;
        } catch (\Throwable $e) {
            session()->flash('error', 'Gagal membuat transfer: '.$e->getMessage());
        }
    }

    public function approve(int $id): void
    {
        $transfer = StockTransfer::findOrFail($id);

        try {
            app(StockTransferService::class)->approve($transfer, (int) auth()->id());
            session()->flash('success', 'Transfer disetujui. Stok keluar dari gudang asal.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function openReceive(int $id): void
    {
        $transfer = StockTransfer::with('items')->findOrFail($id);
        $this->receiveTransferId = $transfer->id;
        $this->receiveItems = $transfer->items
            ->map(fn ($item) => [
                'stock_transfer_item_id' => $item->id,
                'quantity' => (float) $item->quantity - (float) $item->received_quantity,
            ])
            ->values()
            ->all();
        $this->resetErrorBag();
        $this->showReceiveForm = true;
    }

    public function submitReceive(): void
    {
        $transfer = StockTransfer::with('items')->findOrFail($this->receiveTransferId);

        $this->validate([
            'receiveItems' => 'required|array|min:1',
            'receiveItems.*.stock_transfer_item_id' => 'required|integer',
            'receiveItems.*.quantity' => 'required|numeric|min:0',
        ]);

        try {
            app(StockTransferService::class)->receive($transfer, $this->receiveItems, (int) auth()->id());
            session()->flash('success', 'Penerimaan transfer tercatat. Stok masuk ke gudang tujuan.');
            $this->showReceiveForm = false;
            $this->receiveTransferId = null;
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function cancel(int $id): void
    {
        $transfer = StockTransfer::findOrFail($id);

        try {
            app(StockTransferService::class)->cancel($transfer);
            session()->flash('success', 'Transfer dibatalkan.');
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $transfers = StockTransfer::with(['sourceWarehouse', 'destinationWarehouse', 'creator'])
            ->when($this->search, function ($query): void {
                $query->where('transfer_number', 'like', "%{$this->search}%");
            })
            ->latest()
            ->paginate(15);

        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);
        $products = Product::where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name']);

        return view('livewire.stock-transfer-list', compact('transfers', 'warehouses', 'products'));
    }
}
