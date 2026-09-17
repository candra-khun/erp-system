<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\SalesOrderStatus;
use App\Livewire\Concerns\InteractsWithWarehouseAccess;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\SalesOrder;
use App\Rules\WarehouseAccessible;
use App\Services\SalesOrderService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.erp', ['title' => 'Sales Order'])]
class SalesOrderList extends Component
{
    /** @use InteractsWithWarehouseAccess<self> */
    use InteractsWithWarehouseAccess, WithPagination;

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

    /** @var list<array{product_id: string, quantity: int|float|string, unit_price: int|float|string, unit_id: string}> */
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
        $this->items = [['product_id' => '', 'quantity' => 1, 'unit_price' => 0, 'unit_id' => '']];
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function addItem(): void
    {
        $this->items[] = ['product_id' => '', 'quantity' => 1, 'unit_price' => 0, 'unit_id' => ''];
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
            'warehouse_id' => ['required', 'exists:warehouses,id', new WarehouseAccessible],
            'order_date' => 'required|date',
            'delivery_date' => 'nullable|date|after_or_equal:order_date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.unit_id' => 'nullable|exists:product_units,id',
        ]);

        try {
            $salesOrder = DB::transaction(function () {
                $soNumber = $this->generateSoNumber();

                // Konversi ke satuan dasar per item (PRD 4.1 multi-unit).
                // unit_price dari form = harga per satuan PILIHAN -> konversi ke harga per satuan dasar.
                $convertedItems = [];
                foreach ($this->items as $item) {
                    $convertedItems[] = $this->convertItemToBaseUnit($item);
                }

                $subtotal = 0;
                foreach ($convertedItems as $item) {
                    $subtotal += $item['quantity'] * $item['unit_price'];
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

                foreach ($convertedItems as $item) {
                    $lineSubtotal = $item['quantity'] * $item['unit_price'];
                    $salesOrder->items()->create([
                        'product_id' => (int) $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
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

    /**
     * Konversi item ke satuan dasar produk (PRD 4.1 multi-unit).
     * unit_price form = harga per satuan pilihan -> dipecah menjadi harga per satuan dasar.
     *
     * @param  array{product_id: string, quantity: int|float|string, unit_price: int|float|string, unit_id: string}  $item
     * @return array{product_id: int, quantity: float, unit_price: float}
     */
    private function convertItemToBaseUnit(array $item): array
    {
        $quantity = (float) $item['quantity'];
        $unitPrice = (float) $item['unit_price'];

        if (empty($item['unit_id'])) {
            return ['product_id' => (int) $item['product_id'], 'quantity' => $quantity, 'unit_price' => $unitPrice];
        }

        $unit = ProductUnit::findOrFail((int) $item['unit_id']);

        if ($unit->is_base) {
            return ['product_id' => (int) $item['product_id'], 'quantity' => $quantity, 'unit_price' => $unitPrice];
        }

        $product = Product::findOrFail((int) $item['product_id']);
        $baseQty = $product->convertToBaseUnit($quantity, $unit);
        $factor = (float) $unit->conversion_factor;

        if ($factor == 0.0) {
            throw new \RuntimeException("Faktor konversi satuan {$unit->id} nol.");
        }

        // Harga per satuan dasar = harga per satuan pilihan / faktor
        // (1 dus = 12 pcs dijual Rp 60.000 -> Rp 5.000/pcs).
        return [
            'product_id' => (int) $item['product_id'],
            'quantity' => round($baseQty, 4),
            'unit_price' => round($unitPrice / $factor, 4),
        ];
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
            'warehouses' => $this->accessibleWarehouseOptions(),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name']),
            'productUnits' => ProductUnit::orderBy('name')->get(['id', 'name', 'symbol', 'base_unit_id', 'is_base', 'conversion_factor']),
        ]);
    }
}
