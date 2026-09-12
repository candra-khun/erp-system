<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Stock;
use App\Models\StockOpname;
use App\Models\Warehouse;
use App\Services\StockOpnameService;
use Livewire\Component;
use Livewire\WithPagination;

class StockOpnameList extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public string $warehouse_id = '';

    public string $notes = '';

    public array $items = [];

    protected $queryString = ['search'];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->reset(['warehouse_id', 'notes', 'items']);
        $this->showForm = true;
    }

    public function updatedWarehouseId(): void
    {
        $this->loadProducts();
    }

    public function loadProducts(): void
    {
        if (empty($this->warehouse_id)) {
            $this->items = [];

            return;
        }

        $stocks = Stock::where('warehouse_id', $this->warehouse_id)
            ->with('product')
            ->get();

        $this->items = $stocks->map(function ($stock): array {
            return [
                'product_id' => $stock->product_id,
                'product_name' => $stock->product?->name ?? '',
                'sku' => $stock->product?->sku ?? '',
                'system_qty' => (float) $stock->quantity,
                'physical_qty' => (float) $stock->quantity,
                'notes' => '',
            ];
        })->toArray();
    }

    public function store(): void
    {
        $this->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.physical_qty' => 'required|numeric|min:0',
        ]);

        $number = 'OPN-'.now()->format('Ymd').'-'.str_pad((string) (StockOpname::withTrashed()->count() + 1), 4, '0', STR_PAD_LEFT);

        $opname = StockOpname::create([
            'opname_number' => $number,
            'warehouse_id' => $this->warehouse_id,
            'status' => 'draft',
            'notes' => $this->notes !== '' ? $this->notes : null,
            'created_by' => auth()->id(),
        ]);

        foreach ($this->items as $item) {
            $opname->items()->create([
                'product_id' => $item['product_id'],
                'system_quantity' => $item['system_qty'],
                'physical_quantity' => $item['physical_qty'],
                'notes' => $item['notes'] !== '' ? $item['notes'] : null,
            ]);
        }

        session()->flash('success', 'Sesi stock opname berhasil dibuat.');
        $this->showForm = false;
    }

    public function start(int $id): void
    {
        $opname = StockOpname::findOrFail($id);

        if ($opname->status !== 'draft') {
            return;
        }

        $opname->update(['status' => 'in_progress', 'started_at' => now()]);
        session()->flash('success', 'Stock opname dimulai.');
    }

    public function complete(int $id): void
    {
        $opname = StockOpname::findOrFail($id);

        if ($opname->status !== 'in_progress') {
            return;
        }

        $opname->update(['status' => 'completed', 'completed_at' => now()]);
        session()->flash('success', 'Stock opname selesai. Menunggu approval.');
    }

    public function approve(int $id): void
    {
        $opname = StockOpname::with('items')->findOrFail($id);

        if ($opname->status !== 'completed' && $opname->status !== 'draft') {
            return;
        }

        app(StockOpnameService::class)->approve($opname, auth()->id());

        session()->flash('success', 'Penyesuaian stok berhasil disetujui.');
    }

    public function render()
    {
        $opnames = StockOpname::with(['warehouse', 'creator'])
            ->when($this->search, function ($query): void {
                $query->where('opname_number', 'like', "%{$this->search}%");
            })
            ->latest()
            ->paginate(15);

        $warehouses = Warehouse::orderBy('name')->get(['id', 'name']);

        return view('livewire.stock-opname-list', compact('opnames', 'warehouses'))
            ->layout('components.layouts.erp', ['title' => 'Stock Opname']);
    }
}
