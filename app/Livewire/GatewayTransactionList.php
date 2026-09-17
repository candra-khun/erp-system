<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithWarehouseAccess;
use App\Models\GatewayTransaction;
use App\Services\PaymentGateway\PaymentGatewayManager;
use App\Support\WarehouseAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[Layout('components.layouts.erp', ['title' => 'Transaksi Payment Gateway'])]
class GatewayTransactionList extends Component
{
    use InteractsWithWarehouseAccess, WithPagination;

    public string $search = '';

    public string $status = '';

    public ?int $warehouseId = null;

    public ?int $confirmId = null;

    public ?string $paymentReference = null;

    public string $errorMessage = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<GatewayTransaction>
     */
    #[Computed]
    public function transactions()
    {
        return GatewayTransaction::with(['credential', 'payable', 'warehouse'])
            ->accessibleWarehouse($this->accessibleWarehouseIds())
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->search !== '', fn ($q) => $q->where(function ($q): void {
                $q->where('transaction_number', 'like', '%'.$this->search.'%')
                    ->orWhere('gateway_transaction_id', 'like', '%'.$this->search.'%')
                    ->orWhere('payment_reference', 'like', '%'.$this->search.'%');
            }))
            ->orderByDesc('id')
            ->paginate(15);
    }

    /**
     * Buka form konfirmasi pembayaran manual (provider "manual").
     */
    public function openConfirm(int $transactionId): void
    {
        $transaction = $this->resolveAccessibleTransaction($transactionId);

        if ($transaction->isPaid()) {
            $this->errorMessage = 'Transaksi ini sudah dibayar.';

            return;
        }

        $this->confirmId = $transactionId;
        $this->paymentReference = null;
        $this->errorMessage = '';
    }

    /**
     * Tandai transaksi telah dibayar (konfirmasi manual / webhook masuk).
     */
    public function confirmPayment(PaymentGatewayManager $manager): void
    {
        $transaction = $this->resolveAccessibleTransaction((int) $this->confirmId);

        $validated = $this->validate([
            'paymentReference' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $manager->markPaid($transaction, $validated['paymentReference'] ?: null);
            session()->flash('success', 'Pembayaran gateway dikonfirmasi. Settlement tercatat.');
            $this->confirmId = null;
            $this->paymentReference = null;
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function syncStatus(int $transactionId, PaymentGatewayManager $manager): void
    {
        $transaction = $this->resolveAccessibleTransaction($transactionId);

        try {
            $manager->syncStatus($transaction);
            session()->flash('success', 'Status transaksi disinkronkan.');
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function cancelTransaction(int $transactionId, PaymentGatewayManager $manager): void
    {
        $transaction = $this->resolveAccessibleTransaction($transactionId);

        try {
            $manager->cancel($transaction);
            session()->flash('success', 'Transaksi gateway dibatalkan.');
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * @throws HttpException 403 bila transaksi milik cabang lain.
     */
    private function resolveAccessibleTransaction(int $transactionId): GatewayTransaction
    {
        $transaction = GatewayTransaction::findOrFail($transactionId);

        if ($transaction->warehouse_id !== null && ! WarehouseAccess::canAccess(auth()->user(), (int) $transaction->warehouse_id)) {
            abort(403, 'Anda tidak memiliki akses ke transaksi gateway dari cabang ini.');
        }

        return $transaction;
    }

    public function render()
    {
        return view('livewire.gateway-transaction-list', ['transactions' => $this->transactions]);
    }
}
