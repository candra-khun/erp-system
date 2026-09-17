<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithWarehouseAccess;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\Warehouse;
use App\Services\PayrollService;
use App\Support\WarehouseAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[Layout('components.layouts.erp', ['title' => 'Penggajian'])]
class PayrollList extends Component
{
    use InteractsWithWarehouseAccess, WithPagination;

    public string $search = '';

    public ?int $warehouseId = null;

    public bool $showForm = false;

    public ?int $periodYear = null;

    public ?int $periodMonth = null;

    public string $workingDays = '22';

    public string $overtimeHours = '0';

    public string $overtimeRate = '0';

    public ?int $openPeriodId = null;

    public string $errorMessage = '';

    protected function rules(): array
    {
        return [
            'periodYear' => 'required|integer|min:2020|max:2100',
            'periodMonth' => 'required|integer|min:1|max:12',
            'warehouseId' => ['nullable', 'integer'],
            'workingDays' => 'required|numeric|min:0|max:31',
            'overtimeHours' => 'required|numeric|min:0|max:744',
            'overtimeRate' => 'required|numeric|min:0',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        $this->periodYear = (int) now()->format('Y');
        $this->periodMonth = (int) now()->format('m');

        $this->warehouseId = $this->clampWarehouseId($this->warehouseId);
    }

    /**
     * @return LengthAwarePaginator<PayrollPeriod>
     */
    #[Computed]
    public function periods()
    {
        return PayrollPeriod::with(['warehouse', 'payrolls.employee'])
            ->accessibleWarehouse($this->accessibleWarehouseIds())
            ->when($this->warehouseId !== null, fn ($q) => $q->where('warehouse_id', $this->warehouseId))
            ->orderByDesc('year')
            ->orderByDesc('month')
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

    public function createPeriod(PayrollService $service): void
    {
        $validated = $this->validate();

        $warehouseId = $this->clampWarehouseId($validated['warehouseId']);

        try {
            $service->createPeriod(
                (int) $validated['periodYear'],
                (int) $validated['periodMonth'],
                $warehouseId,
                (int) auth()->id(),
            );

            session()->flash('success', 'Periode penggajian berhasil dibuat.');
            $this->showForm = false;
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function generate(int $periodId, PayrollService $service): void
    {
        $period = $this->resolveAccessiblePeriod($periodId);

        try {
            $result = $service->generatePayrolls($period, [
                'working_days' => (float) $this->workingDays,
                'overtime_hours' => (float) $this->overtimeHours,
                'overtime_rate' => (float) $this->overtimeRate,
            ]);

            session()->flash('success', "Berhasil menghasilkan {$result['created']} payroll ({$result['skipped']} dilewati).");
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function approve(int $periodId, PayrollService $service): void
    {
        $period = $this->resolveAccessiblePeriod($periodId);

        try {
            $service->approvePeriod($period, (int) auth()->id());
            session()->flash('success', 'Periode penggajian disetujui. Payroll siap dibayar.');
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function pay(int $payrollId, PayrollService $service): void
    {
        $payroll = Payroll::with('payrollPeriod')->findOrFail($payrollId);

        // Pastikan payroll berada dalam periode di cabang yang dapat diakses user.
        $warehouseId = $payroll->warehouse_id ?? $payroll->payrollPeriod?->warehouse_id;

        if ($warehouseId !== null && ! WarehouseAccess::canAccess(auth()->user(), (int) $warehouseId)) {
            abort(403, 'Anda tidak memiliki akses ke payroll dari cabang ini.');
        }

        try {
            $service->payPayroll($payroll, (int) auth()->id());
            session()->flash('success', 'Gaji berhasil dibayar. Kas keluar & jurnal tercatat.');
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function toggleDetails(int $periodId): void
    {
        $this->openPeriodId = $this->openPeriodId === $periodId ? null : $periodId;
    }

    /**
     * Ambil periode dan pastikan user berhak mengakses cabangnya.
     *
     * @throws HttpException 403 bila periode milik cabang lain.
     */
    private function resolveAccessiblePeriod(int $periodId): PayrollPeriod
    {
        $period = PayrollPeriod::findOrFail($periodId);

        if ($period->warehouse_id !== null && ! WarehouseAccess::canAccess(auth()->user(), (int) $period->warehouse_id)) {
            abort(403, 'Anda tidak memiliki akses ke periode penggajian dari cabang ini.');
        }

        return $period;
    }

    public function render()
    {
        return view('livewire.payroll-list', ['periods' => $this->periods, 'warehouseOptions' => $this->warehouseOptions]);
    }
}
