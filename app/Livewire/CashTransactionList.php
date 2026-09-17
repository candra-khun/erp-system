<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithWarehouseAccess;
use App\Models\CashTransaction;
use App\Rules\WarehouseAccessible;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.erp', ['title' => 'Transaksi Kas'])]
class CashTransactionList extends Component
{
    /** @use InteractsWithWarehouseAccess<self> */
    use InteractsWithWarehouseAccess, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $filterType = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    public bool $showModal = false;

    // Form state
    public string $type = 'in';

    public string $category = 'operational';

    public float $amount = 0;

    public string $transactionDate = '';

    public string $description = '';

    public ?int $warehouseId = null;

    public ?string $paymentMethod = null;

    public function mount(): void
    {
        $this->transactionDate = now()->format('Y-m-d');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterType(): void
    {
        $this->resetPage();
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
        $this->type = 'in';
        $this->category = 'operational';
        $this->amount = 0;
        $this->transactionDate = now()->format('Y-m-d');
        $this->description = '';
        $this->warehouseId = null;
        $this->paymentMethod = null;
        $this->resetErrorBag();
    }

    public function store(): void
    {
        $this->validate([
            'type' => 'required|in:in,out',
            'category' => 'required|in:sales,purchase,operational,salary,other',
            'amount' => 'required|numeric|min:0.01',
            'transactionDate' => 'required|date',
            'description' => 'nullable|string|max:500',
            'warehouseId' => ['nullable', 'exists:warehouses,id', new WarehouseAccessible],
            'paymentMethod' => 'nullable|string|max:50',
        ], [
            'amount.required' => 'Nominal wajib diisi.',
            'amount.min' => 'Nominal minimal 0.01.',
            'transactionDate.required' => 'Tanggal transaksi wajib diisi.',
        ]);

        CashTransaction::create([
            'transaction_number' => $this->generateTransactionNumber(),
            'type' => $this->type,
            'category' => $this->category,
            'amount' => $this->amount,
            'transaction_date' => $this->transactionDate,
            'description' => $this->description ?: null,
            'warehouse_id' => $this->warehouseId,
            'payment_method' => $this->paymentMethod,
            'created_by' => auth()->id(),
        ]);

        session()->flash('message', 'Transaksi kas berhasil dibuat.');
        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $transaction = CashTransaction::findOrFail($id);
        $transaction->delete();
        session()->flash('message', 'Transaksi kas berhasil dihapus.');
    }

    private function generateTransactionNumber(): string
    {
        $datePrefix = 'CT-'.now()->format('Ymd').'-';

        $latest = CashTransaction::where('transaction_number', 'like', $datePrefix.'%')
            ->orderByDesc('transaction_number')
            ->value('transaction_number');

        $sequence = 1;

        if ($latest !== null) {
            $lastSequence = (int) substr($latest, -4);
            $sequence = $lastSequence + 1;
        }

        return $datePrefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function render(): View
    {
        $warehouseIds = $this->accessibleWarehouseIds();

        $query = CashTransaction::with(['warehouse', 'creator'])
            ->when($warehouseIds !== null, fn ($q) => $q->whereIn('warehouse_id', $warehouseIds))
            ->when($this->search, fn ($q) => $q->where(function ($sub) {
                $sub->where('transaction_number', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%");
            }))
            ->when($this->filterType, fn ($q) => $q->where('type', $this->filterType))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('transaction_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('transaction_date', '<=', $this->dateTo))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        $warehouses = $this->accessibleWarehouseOptions();

        return view('livewire.cash-transaction-list', [
            'transactions' => $query->paginate(15),
            'warehouses' => $warehouses,
        ]);
    }
}
