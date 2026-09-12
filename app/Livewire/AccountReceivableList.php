<?php

namespace App\Livewire;

use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Services\FinanceService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.erp', ['title' => 'Piutang Pelanggan'])]
class AccountReceivableList extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    public bool $showCreateModal = false;

    public bool $showPayModal = false;

    // Create form state
    public ?int $customer_id = null;

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
        $this->customer_id = null;
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
            'customer_id' => 'required|exists:customers,id',
            'total_amount' => 'required|numeric|min:0.01',
            'due_date' => 'required|date',
            'notes' => 'nullable|string',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|integer',
        ], [
            'customer_id.required' => 'Pilih pelanggan terlebih dahulu.',
            'total_amount.required' => 'Total piutang wajib diisi.',
            'total_amount.min' => 'Total piutang minimal 0.01.',
            'due_date.required' => 'Tanggal jatuh tempo wajib diisi.',
        ]);

        $arNumber = $this->generateArNumber();
        $totalAmount = (float) $this->total_amount;

        AccountReceivable::create([
            'ar_number' => $arNumber,
            'customer_id' => $this->customer_id,
            'total_amount' => $totalAmount,
            'paid_amount' => 0,
            'remaining_amount' => $totalAmount,
            'due_date' => $this->due_date,
            'status' => 'open',
            'notes' => $this->notes ?: null,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
        ]);

        session()->flash('message', 'Piutang pelanggan berhasil dibuat.');
        $this->closeCreateModal();
    }

    public function openPayModal(int $id): void
    {
        $ar = AccountReceivable::findOrFail($id);

        if ($ar->status === 'paid') {
            session()->flash('error', 'Piutang ini sudah lunas.');

            return;
        }

        $this->payId = $ar->id;
        $this->pay_amount = (string) $ar->remaining_amount;
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

        $ar = AccountReceivable::findOrFail($this->payId);

        try {
            app(FinanceService::class)->collectReceivable($ar, (float) $this->pay_amount, auth()->id());

            session()->flash('message', 'Pembayaran berhasil dicatat (kas masuk + jurnal otomatis).');
            $this->closePayModal();
        } catch (\RuntimeException $e) {
            $this->addError('pay_amount', $e->getMessage());
        }
    }

    public function delete(int $id): void
    {
        $ar = AccountReceivable::findOrFail($id);

        if ($ar->status !== 'open') {
            session()->flash('error', 'Hanya piutang berstatus terbuka yang bisa dihapus.');

            return;
        }

        $ar->delete();
        session()->flash('message', 'Piutang pelanggan berhasil dihapus.');
    }

    private function generateArNumber(): string
    {
        $datePrefix = 'AR-'.now()->format('Ymd').'-';

        $latest = AccountReceivable::where('ar_number', 'like', $datePrefix.'%')
            ->orderByDesc('ar_number')
            ->value('ar_number');

        $sequence = 1;

        if ($latest !== null) {
            $lastSequence = (int) substr($latest, -4);
            $sequence = $lastSequence + 1;
        }

        return $datePrefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function render(): View
    {
        $query = AccountReceivable::with('customer')
            ->when($this->search, fn ($q) => $q->where('ar_number', 'like', "%{$this->search}%"))
            ->when($this->statusFilter, function ($q) {
                if ($this->statusFilter === 'overdue') {
                    $q->whereIn('status', ['open', 'partial'])->where('due_date', '<', now());
                } else {
                    $q->where('status', $this->statusFilter);
                }
            })
            ->orderByDesc('created_at');

        return view('livewire.account-receivable-list', [
            'receivables' => $query->paginate(15),
            'customers' => Customer::orderBy('name')->get(),
        ]);
    }
}
