<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithWarehouseAccess;
use App\Models\ConsignmentSettlement;
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

#[Layout('components.layouts.erp', ['title' => 'Settlement Konsinyasi'])]
class ConsignmentSettlementList extends Component
{
    use InteractsWithWarehouseAccess, WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $supplierId = null;

    public ?int $warehouseId = null;

    public string $commission = '0';

    public string $notes = '';

    public string $errorMessage = '';

    protected function rules(): array
    {
        return [
            'supplierId' => ['required', 'integer', 'exists:suppliers,id'],
            'warehouseId' => ['nullable', 'integer'],
            'commission' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<ConsignmentSettlement>
     */
    #[Computed]
    public function settlements()
    {
        return ConsignmentSettlement::with(['supplier', 'warehouse', 'items.product', 'accountPayable'])
            ->accessibleWarehouse($this->accessibleWarehouseIds())
            ->when($this->search !== '', fn ($q) => $q->where(function ($q): void {
                $q->where('settlement_number', 'like', '%'.$this->search.'%')
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', '%'.$this->search.'%'));
            }))
            ->orderByDesc('id')
            ->paginate(15);
    }

    public function openCreate(): void
    {
        $this->reset(['commission', 'notes', 'errorMessage']);
        $this->commission = '0';
        $this->showForm = true;
    }

    public function create(ConsignmentService $service): void
    {
        $validated = $this->validate();

        $warehouseId = $this->clampWarehouseId($validated['warehouseId']);

        try {
            $settlement = $service->createSettlement(
                (int) $validated['supplierId'],
                $warehouseId,
                (int) auth()->id(),
                ['commission' => (float) $validated['commission'], 'notes' => $validated['notes'] ?: null],
            );

            session()->flash('success', 'Settlement '.$settlement->settlement_number.' dibuat: '.number_format((float) $settlement->total_amount, 2, ',', '.'));
            $this->showForm = false;
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function confirm(int $settlementId, ConsignmentService $service): void
    {
        $settlement = $this->resolveAccessibleSettlement($settlementId);

        try {
            $service->confirmSettlement($settlement, (int) auth()->id());
            session()->flash('success', 'Settlement dikonfirmasi. Hutang ke supplier (AP) terbentuk + jurnal tercatat.');
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * @throws HttpException 403 bila settlement milik cabang lain.
     */
    private function resolveAccessibleSettlement(int $settlementId): ConsignmentSettlement
    {
        $settlement = ConsignmentSettlement::findOrFail($settlementId);

        if ($settlement->warehouse_id !== null && ! WarehouseAccess::canAccess(auth()->user(), (int) $settlement->warehouse_id)) {
            abort(403, 'Anda tidak memiliki akses ke settlement dari cabang ini.');
        }

        return $settlement;
    }

    /**
     * @return Collection<int, Supplier>
     */
    #[Computed]
    public function supplierOptions()
    {
        return Supplier::orderBy('name')->where('is_active', true)->get(['id', 'name']);
    }

    /**
     * @return Collection<int, Warehouse>
     */
    #[Computed]
    public function warehouseOptions()
    {
        return $this->accessibleWarehouseOptions();
    }

    public function render()
    {
        return view('livewire.consignment-settlement-list', ['settlements' => $this->settlements, 'warehouseOptions' => $this->warehouseOptions, 'supplierOptions' => $this->supplierOptions]);
    }
}
