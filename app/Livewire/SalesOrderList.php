<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\SalesOrderStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use App\Services\SalesOrderService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.erp', ['title' => 'Sales Order'])]
class SalesOrderList extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    public bool $showForm = false;

    public string $customer_id = '';

    public string $warehouse_id = '';

    public string $order_date = '';

    public string $delivery_date = '';

    public string $notes = '';

    /** @var list<array{product_id: string, quantity: int|float|string, unit_price: int|float|string}> */
    public array $items = [];

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
        $this->reset(['customer_id', 'warehouse_id', 'delivery_date', 'notes', 'items']);
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
            'customer_id' => 'required|exists:customers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'order_date' => 'required|date',
            'delivery_date' => 'nullable|date|after_or_equal:order_date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            $salesOrder = DB::transaction(function () {
                $soNumber = $this->generateSoNumber();

                $subtotal = 0;
                foreach ($this->items as $item) {
                    $subtotal += ((float) $item['quantity']) * ((float) $item['unit_price']);
                }

                $salesOrder = SalesOrder::create([
                    'so_number' => $soNumber,
                    'customer_id' => (int) $this->customer_id,
                    'warehouse_id' => (int) $this->warehouse_id,
                    'order_date' => $this->order_date,
                    'delivery_date' => $this->delivery_date ?: null,
                    'notes' => $this->notes ?: null,
                    'status' => 'draft',
                    'subtotal' => $subtotal,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'total_amount' => $subtotal,
                    'paid_amount' => 0,
                    'payment_status' => 'unpaid',
                    'created_by' => auth()->id(),
                ]);

                foreach ($this->items as $item) {
                    $lineSubtotal = ((float) $item['quantity']) * ((float) $item['unit_price']);
                    $salesOrder->items()->create([
                        'product_id' => (int) $item['product_id'],
                        'quantity' => (float) $item['quantity'],
                        'unit_price' => (float) $item['unit_price'],
                        'discount_amount' => 0,
                        'subtotal' => $lineSubtotal,
                    ]);
                }

                return $salesOrder;
            });

            session()->flash('success', "Sales Order {$salesOrder->so_number} berhasil dibuat (draft).");
            $this->showForm = false;
        } catch (\Throwable $e) {
            session()->flash('error', 'Gagal membuat SO: '.$e->getMessage());
        }
    }

    public function confirm(int $id): void
    {
        $salesOrder = SalesOrder::findOrFail($id);

        try {
            app(SalesOrderService::class)->confirm($salesOrder, (int) auth()->id());
            session()->flash('success', "SO {$salesOrder->so_number} dikonfirmasi. Stok berkurang.");
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function cancel(int $id): void
    {
        $salesOrder = SalesOrder::findOrFail($id);

        try {
            app(SalesOrderService::class)->cancel($salesOrder, (int) auth()->id());
            session()->flash('success', "SO {$salesOrder->so_number} dibatalkan.");
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function printInvoice(int $id): void
    {
        $this->redirectRoute('pdf.invoice', ['id' => $id], navigate: false);
    }

    private function generateSoNumber(): string
    {
        $prefix = 'SO-'.now()->format('Ymd').'-';

        $last = SalesOrder::where('so_number', 'like', $prefix.'%')
            ->orderByDesc('so_number')
            ->value('so_number');

        $seq = $last ? ((int) substr((string) $last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function delete(int $id): void
    {
        $salesOrder = SalesOrder::findOrFail($id);

        if ($salesOrder->status !== 'draft') {
            session()->flash('error', 'Hanya SO berstatus draft yang dapat dihapus.');

            return;
        }

        $salesOrder->items()->delete();
        $salesOrder->delete();
        session()->flash('success', 'Sales Order berhasil dihapus (soft delete).');
    }

    public function render()
    {
        $query = SalesOrder::with(['customer', 'items'])
            ->when($this->search, fn ($q) => $q->where('so_number', 'like', "%{$this->search}%"))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('order_date');

        return view('livewire.sales-order-list', [
            'salesOrders' => $query->paginate(15),
            'statuses' => SalesOrderStatus::cases(),
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name']),
        ]);
    }
}
