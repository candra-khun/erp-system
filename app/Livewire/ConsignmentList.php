<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithWarehouseAccess;
use App\Models\ConsignmentIn;
use App\Models\ConsignmentItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\ConsignmentService;
use App\Support\WarehouseAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[Layout('components.layouts.erp', ['title' => 'Penerimaan Konsinyasi'])]
class ConsignmentList extends Component
{
    use InteractsWithWarehouseAccess, WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $supplierId = null;

    public ?int $warehouseId = null;

    public ?string $expiry_date = null;

    public string $notes = '';

    public array $lines = [];

    public string $errorMessage = '';

    protected function rules(): array
    {
        return [
            'supplierId' => ['required', 'integer', 'exists:suppliers,id'],
            'warehouseId' => ['nullable', 'integer'],
            'expiry_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.consignment_price' => ['required', 'numeric', 'min:0'],
            'lines.*.selling_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<ConsignmentIn>
     */
    #[Computed]
    public function consignments()
    {
        return ConsignmentIn::with(['supplier', 'warehouse', 'items.product'])
            ->accessibleWarehouse($this->accessibleWarehouseIds())
            ->when($this->search !== '', fn ($q) => $q->where(function ($q): void {
                $q->where('consignment_number', 'like', '%'.$this->search.'%')
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', '%'.$this->search.'%'));
            }))
            ->orderByDesc('id')
            ->paginate(15);
    }

    /**
     * @return Collection<int, Warehouse>
     */
    #[Computed]
    public function warehouseOptions()
    {
        return $this->accessibleWarehouseOptions();
    }

    public function openCreate(): void
    {
        $this->reset(['supplierId', 'expiry_date', 'notes', 'errorMessage']);
        $this->warehouseId = $this->clampWarehouseId($this->warehouseId);
        $this->lines = [['product_id' => 0, 'quantity' => 1, 'consignment_price' => 0, 'selling_price' => 0]];
        $this->showForm = true;
    }

    public function addLine(): void
    {
        $this->lines[] = ['product_id' => 0, 'quantity' => 1, 'consignment_price' => 0, 'selling_price' => 0];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function save(ConsignmentService $service): void
    {
        $validated = $this->validate();

        $warehouseId = $this->clampWarehouseId($validated['warehouseId']);

        try {
            $consignment = $service->receiveConsignment(
                (int) $validated['supplierId'],
                $warehouseId,
                $validated['lines'],
                (int) auth()->id(),
                $validated['notes'] ?: null,
            );

            session()->flash('success', 'Penerimaan konsinyasi '.$consignment->consignment_number.' berhasil. Stok gudang naik.');
            $this->showForm = false;
            $this->lines = [];
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Kembalikan barang konsinyasi yang belum terjual ke supplier.
     */
    public function returnItem(int $itemId, string $quantity, ConsignmentService $service): void
    {
        $item = ConsignmentItem::with('consignmentIn')->findOrFail($itemId);
        $warehouseId = $item->consignmentIn?->warehouse_id;

        if ($warehouseId !== null && ! WarehouseAccess::canAccess(auth()->user(), (int) $warehouseId)) {
            abort(403, 'Anda tidak memiliki akses ke konsinyasi dari cabang ini.');
        }

        try {
            $service->returnConsignment($item, (float) $quantity);
            session()->flash('success', 'Barang konsinyasi dikembalikan ke supplier.');
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * @return Collection<int, Product>
     */
    #[Computed]
    public function productOptions()
    {
        return Product::orderBy('name')->where('is_active', true)->get(['id', 'name', 'sku', 'selling_price']);
    }

    /**
     * @return Collection<int, Supplier>
     */
    #[Computed]
    public function supplierOptions()
    {
        return Supplier::orderBy('name')->where('is_active', true)->get(['id', 'name']);
    }

    public function render()
    {
        return view('livewire.consignment-list', ['consignments' => $this->consignments, 'warehouseOptions' => $this->warehouseOptions, 'productOptions' => $this->productOptions, 'supplierOptions' => $this->supplierOptions]);
    }
}
