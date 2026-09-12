<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Events\StockChanged;
use App\Models\SalesOrder;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class SalesReturnList extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public string $sales_order_id = '';

    public string $notes = '';

    public array $items = [];

    protected $queryString = ['search'];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->reset(['sales_order_id', 'notes', 'items']);
        $this->showForm = true;
    }

    public function updatedSalesOrderId(): void
    {
        $this->loadItems();
    }

    public function loadItems(): void
    {
        $this->items = [];
        if (empty($this->sales_order_id)) {
            return;
        }

        $order = SalesOrder::with('items.product')->find($this->sales_order_id);
        if ($order) {
            foreach ($order->items as $item) {
                $this->items[] = [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name ?? '',
                    'sku' => $item->product?->sku ?? '',
                    'order_qty' => $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'return_quantity' => $item->quantity,
                    'restock' => true,
                    'reason' => '',
                ];
            }
        }
    }

    public function save(): void
    {
        $this->validate([
            'sales_order_id' => 'required|exists:sales_orders,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.return_quantity' => 'required|numeric|min:0.01',
        ]);

        DB::transaction(function (): void {
            $order = SalesOrder::findOrFail($this->sales_order_id);
            $returnNumber = 'SR-'.now()->format('Ymd').'-'.str_pad((string) (SalesReturn::whereDate('created_at', today())->count() + 1), 4, '0', STR_PAD_LEFT);

            $salesReturn = SalesReturn::create([
                'return_number' => $returnNumber,
                'returnable_type' => SalesOrder::class,
                'returnable_id' => $order->id,
                'customer_id' => $order->customer_id,
                'return_date' => now()->toDateString(),
                'status' => 'draft',
                'reason' => $this->notes !== '' ? $this->notes : null,
                'total_amount' => 0,
                'created_by' => auth()->id(),
            ]);

            $totalAmount = 0;
            foreach ($this->items as $item) {
                if ((float) $item['return_quantity'] <= 0) {
                    continue;
                }

                $subtotal = (float) $item['return_quantity'] * (float) ($item['unit_price'] ?? 0);
                $totalAmount += $subtotal;

                SalesReturnItem::create([
                    'sales_return_id' => $salesReturn->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['return_quantity'],
                    'unit_price' => $item['unit_price'] ?? 0,
                    'subtotal' => $subtotal,
                    'restock' => (bool) ($item['restock'] ?? true),
                    'reason' => $item['reason'] ?? null,
                ]);
            }

            $salesReturn->update(['total_amount' => $totalAmount]);
        });

        session()->flash('success', 'Retur penjualan berhasil dibuat.');
        $this->showForm = false;
        $this->resetPage();
    }

    public function approve(int $id): void
    {
        $return = SalesReturn::with('items')->findOrFail($id);
        if ($return->status !== 'draft') {
            return;
        }

        $warehouseId = null;
        if ($return->returnable_type === SalesOrder::class) {
            $warehouseId = $return->returnable?->warehouse_id;
        }

        DB::transaction(function () use ($return, $warehouseId): void {
            foreach ($return->items as $item) {
                if (! $item->restock || ! $warehouseId) {
                    continue;
                }

                event(new StockChanged(
                    productId: $item->product_id,
                    warehouseId: $warehouseId,
                    type: 'in',
                    quantity: (float) $item->quantity,
                    referenceType: SalesReturn::class,
                    referenceId: $return->id,
                    userId: auth()->id(),
                    notes: 'Retur penjualan: '.$return->return_number,
                ));
            }

            $return->update(['status' => 'approved']);
        });

        session()->flash('success', 'Retur penjualan disetujui. Stok dikembalikan.');
    }

    public function cancel(int $id): void
    {
        $return = SalesReturn::findOrFail($id);

        if ($return->status !== 'draft') {
            return;
        }

        $return->update(['status' => 'cancelled']);
        session()->flash('success', 'Retur penjualan dibatalkan.');
    }

    public function render()
    {
        $returns = SalesReturn::with(['customer', 'returnable', 'creator'])
            ->when($this->search, function ($query): void {
                $query->where('return_number', 'like', "%{$this->search}%");
            })
            ->latest()
            ->paginate(15);

        $salesOrders = SalesOrder::where('status', '!=', 'cancelled')
            ->orderByDesc('created_at')
            ->get(['id', 'so_number', 'customer_id']);

        return view('livewire.sales-return-list', compact('returns', 'salesOrders'))
            ->layout('components.layouts.erp', ['title' => 'Retur Penjualan']);
    }
}
