<?php

namespace App\Livewire;

use App\Models\AccountPayable;
use App\Models\Supplier;
use App\Services\FinanceService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.erp', ['title' => 'Hutang Supplier'])]
class AccountPayableList extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    public bool $showCreateModal = false;

    public bool $showPayModal = false;

    // Create form state
    public ?int $supplier_id = null;

    public string $total_amount = '';

    public string $due_date = '';

    public string $notes = '';

    public ?string $reference_type = null;

    public ?int $reference_id = null;

    // Pay modal state
    public ?int $payId = null;

    public string $pay_amount = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetForm();
    }

    public function closeModals(): void
    {
        $this->showCreateModal = false;
        $this->showPayModal = false;
        $this->resetForm();
        $this->closePayModal();
    }

    private function resetForm(): void
    {
        $this->supplier_id = null;
        $this->total_amount = '';
        $this->due_date = now()->addDays(30)->format('Y-m-d');
        $this->notes = '';
        $this->reference_type = null;
        $this->reference_id = null;
        $this->resetErrorBag();
    }

    public function store(): void
    {
        $this->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'total_amount' => 'required|numeric|min:0.01',
            'due_date' => 'required|date',
            'notes' => 'nullable|string',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|integer',
        ], [
            'supplier_id.required' => 'Pilih supplier terlebih dahulu.',
            'total_amount.required' => 'Total hutang wajib diisi.',
            'total_amount.min' => 'Total hutang minimal 0.01.',
            'due_date.required' => 'Tanggal jatuh tempo wajib diisi.',
        ]);

        $apNumber = $this->generateApNumber();
        $totalAmount = (float) $this->total_amount;

        AccountPayable::create([
            'ap_number' => $apNumber,
            'supplier_id' => $this->supplier_id,
            'total_amount' => $totalAmount,
            'paid_amount' => 0,
            'remaining_amount' => $totalAmount,
            'due_date' => $this->due_date,
            'status' => 'open',
            'notes' => $this->notes ?: null,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
        ]);

        session()->flash('message', 'Hutang supplier berhasil dibuat.');
        $this->closeCreateModal();
    }

    public function openPayModal(int $id): void
    {
        $ap = AccountPayable::findOrFail($id);

        if ($ap->status === 'paid') {
            session()->flash('error', 'Hutang ini sudah lunas.');

            return;
        }

        $this->payId = $ap->id;
        $this->pay_amount = (string) $ap->remaining_amount;
        $this->showPayModal = true;
        $this->resetErrorBag();
    }

    public function closePayModal(): void
    {
        $this->showPayModal = false;
        $this->payId = null;
        $this->pay_amount = '';
        $this->resetErrorBag();
    }

    public function pay(): void
    {
        $this->validate([
            'pay_amount' => 'required|numeric|min:0.01',
        ], [
            'pay_amount.required' => 'Jumlah pembayaran wajib diisi.',
            'pay_amount.min' => 'Jumlah pembayaran minimal 0.01.',
        ]);

        $ap = AccountPayable::findOrFail($this->payId);

        try {
            app(FinanceService::class)->payPayable($ap, (float) $this->pay_amount, auth()->id());

            session()->flash('message', 'Pembayaran berhasil dicatat (kas keluar + jurnal otomatis).');
            $this->closePayModal();
        } catch (\RuntimeException $e) {
            $this->addError('pay_amount', $e->getMessage());
        }
    }

    public function delete(int $id): void
    {
        $ap = AccountPayable::findOrFail($id);

        if ($ap->status !== 'open') {
            session()->flash('error', 'Hanya hutang berstatus terbuka yang bisa dihapus.');

            return;
        }

        $ap->delete();
        session()->flash('message', 'Hutang supplier berhasil dihapus.');
    }

    private function generateApNumber(): string
    {
        $datePrefix = 'AP-'.now()->format('Ymd').'-';

        $latest = AccountPayable::where('ap_number', 'like', $datePrefix.'%')
            ->orderByDesc('ap_number')
            ->value('ap_number');

        $sequence = 1;

        if ($latest !== null) {
            $lastSequence = (int) substr($latest, -4);
            $sequence = $lastSequence + 1;
        }

        return $datePrefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function render(): View
    {
        $query = AccountPayable::with('supplier')
            ->when($this->search, fn ($q) => $q->where('ap_number', 'like', "%{$this->search}%"))
            ->when($this->statusFilter, function ($q) {
                if ($this->statusFilter === 'overdue') {
                    $q->whereIn('status', ['open', 'partial'])->where('due_date', '<', now());
                } else {
                    $q->where('status', $this->statusFilter);
                }
            })
            ->orderByDesc('created_at');

        return view('livewire.account-payable-list', [
            'payables' => $query->paginate(15),
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }
}
