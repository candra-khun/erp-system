<?php

namespace App\Livewire;

use App\Imports\BankStatementImport;
use App\Livewire\Concerns\InteractsWithWarehouseAccess;
use App\Models\BankReconciliation;
use App\Models\BankStatementLine;
use App\Models\CashTransaction;
use App\Models\Warehouse;
use App\Rules\WarehouseAccessible;
use App\Services\BankReconciliationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BankReconciliationList extends Component
{
    /** @use InteractsWithWarehouseAccess<self> */
    use InteractsWithWarehouseAccess, WithFileUploads, WithPagination;

    public bool $showForm = false;

    public ?int $warehouseId = null;

    public string $periodStart = '';

    public string $periodEnd = '';

    public float $bankStatementBalance = 0;

    public ?string $notes = null;

    /** @var list<array{value_date: string, description: string, amount: float}> */
    public array $lines = [];

    /** File Excel/CSV rekening koran untuk impor baris. */
    public ?UploadedFile $statementFile = null;

    public ?int $openSessionId = null;

    public string $errorMessage = '';

    public function openForm(): void
    {
        $this->resetValidation();
        $this->errorMessage = '';
        $this->showForm = true;
    }

    public function addLine(): void
    {
        $this->lines[] = ['value_date' => now()->toDateString(), 'description' => '', 'amount' => 0];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    /**
     * Import baris rekening koran dari Excel/CSV (PRD 4.5 — Rekonsiliasi Bank).
     * Baris yang berhasil menggantikan/menambah input manual pada form.
     */
    public function importStatement(UploadedFile $file): void
    {
        $this->validate([
            'statementFile' => 'required|file|mimes:xlsx,xls,csv,txt|max:2048',
        ]);

        try {
            $import = new BankStatementImport;
            Excel::import($import, $file);
            $lines = $import->lines;

            if ($import->imported === 0) {
                $this->errorMessage = 'Tidak ada baris yang berhasil dibaca dari file.';

                return;
            }

            $this->lines = array_map(
                fn (array $line): array => [
                    'value_date' => $line['value_date'],
                    'description' => $line['description'],
                    'amount' => $line['amount'],
                ],
                $lines,
            );

            $this->errorMessage = $import->errors === []
                ? ''
                : 'Diimpor '.$import->imported.' baris. '.implode(' ', array_slice($import->errors, 0, 5));

            if ($import->errors !== []) {
                $this->addError('statementFile', 'Sebagian baris dilewati.');
            }
        } catch (\Throwable $e) {
            $this->errorMessage = 'Gagal membaca file: '.$e->getMessage();
        }
    }

    public function saveSession(BankReconciliationService $service): void
    {
        // Clamp filter gudang ke scope akses user (PRD §2: Admin Cabang = 1 cabang)
        $this->warehouseId = $this->clampWarehouseId($this->warehouseId);

        $this->validate([
            'warehouseId' => ['nullable', 'exists:warehouses,id', new WarehouseAccessible],
            'periodStart' => 'required|date',
            'periodEnd' => 'required|date|after_or_equal:periodStart',
            'bankStatementBalance' => 'required|numeric',
            'lines' => 'required|array|min:1',
            'lines.*.value_date' => 'required|date',
            'lines.*.amount' => 'required|numeric',
        ]);

        try {
            $session = $service->createSession(
                [
                    'warehouse_id' => $this->warehouseId,
                    'period_start' => $this->periodStart,
                    'period_end' => $this->periodEnd,
                    'bank_statement_balance' => $this->bankStatementBalance,
                    'notes' => $this->notes,
                ],
                $this->lines,
                (int) Auth::id(),
            );

            $this->showForm = false;
            $this->openSessionId = $session->id;
            $this->lines = [];
            session()->flash('success', 'Sesi rekonsiliasi dibuat: '.$session->reconciliation_number);
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * Ambil sesi rekonsiliasi dan pastikan user berhak mengakses cabangnya.
     *
     * @throws HttpException 403 bila sesi milik cabang lain.
     */
    private function resolveAccessibleSession(int $sessionId): BankReconciliation
    {
        $session = BankReconciliation::findOrFail($sessionId);
        $ids = $this->accessibleWarehouseIds();

        if ($ids !== null
            && ($session->warehouse_id === null || ! in_array((int) $session->warehouse_id, $ids, true))) {
            abort(403, 'Anda tidak memiliki akses ke sesi rekonsiliasi ini.');
        }

        return $session;
    }

    public function rematch(int $sessionId, BankReconciliationService $service): void
    {
        $session = $this->resolveAccessibleSession($sessionId);
        $service->autoMatch($session);
        $service->recomputeBalances($session);
        session()->flash('success', 'Pencocokan otomatis diulang.');
    }

    public function completeSession(int $sessionId, BankReconciliationService $service): void
    {
        try {
            $service->complete($this->resolveAccessibleSession($sessionId), (int) Auth::id());
            session()->flash('success', 'Rekonsiliasi selesai.');
        } catch (RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function cancelSession(int $sessionId): void
    {
        $session = $this->resolveAccessibleSession($sessionId);

        if ($session->status === 'open') {
            $session->update(['status' => 'cancelled']);
            session()->flash('success', 'Sesi rekonsiliasi dibatalkan.');
        }
    }

    public function setLineMatch(int $lineId, ?int $cashTransactionId, BankReconciliationService $service): void
    {
        try {
            $line = BankStatementLine::findOrFail($lineId);
            $this->resolveAccessibleSession((int) $line->bank_reconciliation_id);
            $service->setMatch($line, $cashTransactionId);
            $service->recomputeBalances($line->reconciliation);
        } catch (RuntimeException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /** @return list<Warehouse> */
    #[Computed]
    public function warehouses(): array
    {
        $ids = $this->accessibleWarehouseIds();

        return Warehouse::when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('name')
            ->get()
            ->all();
    }

    /** @return LengthAwarePaginator<BankReconciliation> */
    #[Computed]
    public function sessions()
    {
        $ids = $this->accessibleWarehouseIds();

        return BankReconciliation::with('warehouse')
            ->when($ids !== null, fn ($q) => $q->whereIn('warehouse_id', $ids))
            ->orderByDesc('id')
            ->paginate(10);
    }

    #[Computed]
    public function openSession()
    {
        if (! $this->openSessionId) {
            return null;
        }

        $session = BankReconciliation::with(['statementLines.matchedTransaction', 'warehouse'])
            ->find($this->openSessionId);

        $ids = $this->accessibleWarehouseIds();

        if ($session !== null && $ids !== null
            && ($session->warehouse_id === null || ! in_array((int) $session->warehouse_id, $ids, true))) {
            abort(403, 'Anda tidak memiliki akses ke sesi rekonsiliasi ini.');
        }

        return $session;
    }

    /** Kandidat transaksi kas untuk dicocokkan manual. */
    public function candidatesFor(BankStatementLine $line)
    {
        $ids = $this->accessibleWarehouseIds();

        return CashTransaction::whereBetween('transaction_date', [$line->value_date->copy()->subDays(7), $line->value_date->copy()->addDays(7)])
            ->when($ids !== null, fn ($q) => $q->whereIn('warehouse_id', $ids))
            ->orderBy('transaction_date')
            ->limit(20)
            ->get();
    }

    public function render()
    {
        return view('livewire.bank-reconciliation-list')
            ->layout('components.layouts.erp', ['title' => 'Rekonsiliasi Bank']);
    }
}
