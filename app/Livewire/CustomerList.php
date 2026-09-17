<?php

namespace App\Livewire;

use App\Enums\CustomerType;
use App\Models\Customer;
use App\Models\CustomerCommunication;
use App\Models\CustomerLoyaltyProfile;
use App\Models\LoyaltyPoint;
use App\Services\LoyaltyService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.erp')]
class CustomerList extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public bool $isEdit = false;

    public ?int $customerId = null;

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public string $customer_type = '';

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'customer_type' => 'required|string',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->isEdit = false;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $customer = Customer::findOrFail($id);
        $this->customerId = $customer->id;
        $this->name = $customer->name ?? '';
        $this->phone = $customer->phone ?? '';
        $this->email = $customer->email ?? '';
        $this->address = $customer->address ?? '';
        $this->customer_type = $customer->type ?? '';
        $this->isEdit = true;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'type' => $this->customer_type,
        ];

        if ($this->isEdit && $this->customerId) {
            Customer::findOrFail($this->customerId)->update($data);
            Session::flash('message', 'Customer berhasil diperbarui.');
        } else {
            Customer::create($data);
            Session::flash('message', 'Customer berhasil ditambahkan.');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    #[On('delete-customer')]
    public function delete(int $id): void
    {
        Customer::findOrFail($id)->delete();
        Session::flash('message', 'Customer berhasil dihapus.');
    }

    // === Loyalitas & Komunikasi (Fase 3 — PRD 4.6) ===

    public bool $showLoyalty = false;

    public ?int $loyaltyCustomerId = null;

    public int $redeemPoints = 0;

    public ?string $redeemNotes = null;

    public string $commChannel = 'phone';

    public string $commSubject = '';

    public string $commSummary = '';

    public ?string $commFollowUpDate = null;

    public function openLoyalty(int $id): void
    {
        $this->loyaltyCustomerId = $id;
        $this->showLoyalty = true;
        $this->resetValidation();
        $this->redeemPoints = 0;
        $this->redeemNotes = null;
    }

    #[Computed]
    public function loyaltyCustomer(): ?Customer
    {
        return $this->loyaltyCustomerId ? Customer::find($this->loyaltyCustomerId) : null;
    }

    #[Computed]
    public function loyaltyProfile()
    {
        return $this->loyaltyCustomerId
            ? CustomerLoyaltyProfile::firstOrCreate(
                ['customer_id' => $this->loyaltyCustomerId],
                ['points_balance' => 0, 'lifetime_points' => 0],
            )
            : null;
    }

    #[Computed]
    public function loyaltyLogs()
    {
        return $this->loyaltyCustomerId
            ? LoyaltyPoint::where('customer_id', $this->loyaltyCustomerId)->latest()->limit(10)->get()
            : collect();
    }

    #[Computed]
    public function communications()
    {
        return $this->loyaltyCustomerId
            ? CustomerCommunication::where('customer_id', $this->loyaltyCustomerId)->with('creator')->latest()->limit(10)->get()
            : collect();
    }

    public function redeem(LoyaltyService $service): void
    {
        $this->validate(['redeemPoints' => 'required|integer|min:1']);

        try {
            $service->redeem($this->loyaltyCustomerId, $this->redeemPoints, 'manual', null, (int) Auth::id(), $this->redeemNotes);
            Session::flash('message', 'Poin berhasil ditukarkan.');
            $this->redeemPoints = 0;
            $this->redeemNotes = null;
        } catch (\RuntimeException $e) {
            $this->addError('redeemPoints', $e->getMessage());
        }
    }

    public function addCommunication(): void
    {
        $this->validate([
            'commChannel' => 'required|in:phone,whatsapp,email,visit,other',
            'commSummary' => 'required|string|max:2000',
            'commSubject' => 'nullable|string|max:200',
            'commFollowUpDate' => 'nullable|date',
        ]);

        CustomerCommunication::create([
            'customer_id' => $this->loyaltyCustomerId,
            'channel' => $this->commChannel,
            'subject' => $this->commSubject,
            'summary' => $this->commSummary,
            'follow_up_date' => $this->commFollowUpDate,
            'created_by' => Auth::id(),
        ]);

        $this->commSummary = '';
        $this->commSubject = '';
        $this->commFollowUpDate = null;
        Session::flash('message', 'Riwayat komunikasi dicatat.');
    }

    private function resetForm(): void
    {
        $this->customerId = null;
        $this->name = '';
        $this->phone = '';
        $this->email = '';
        $this->address = '';
        $this->customer_type = CustomerType::General->value;
        $this->resetValidation();
    }

    public function render()
    {
        $customers = Customer::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")
                ->orWhere('phone', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(10);

        return view('livewire.customer-list', compact('customers'));
    }
}
