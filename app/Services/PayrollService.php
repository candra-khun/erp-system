<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PayrollComponentType;
use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollStatus;
use App\Models\Account;
use App\Models\CashTransaction;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollComponent;
use App\Models\PayrollComponentLine;
use App\Models\PayrollPeriod;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * HR & Payroll service (PRD Fase 4 #12).
 *
 * Mengelola periode penggajian: membuat periode, menghasilkan payroll per
 * karyawan (gaji pokok + komponen tetap + lembur), menyetujui, dan
 * membayar (kas keluar + jurnal otomatis Dr Biaya Gaji / Cr Kas).
 */
class PayrollService
{
    public function __construct(private readonly JournalService $journalService) {}

    /**
     * Buat (atau ambil) periode penggajian untuk bulan + cabang tertentu.
     *
     * @throws RuntimeException bila periode sudah ada dan bukan draft/cancelled.
     */
    public function createPeriod(int $year, int $month, ?int $warehouseId, ?int $userId, ?string $notes = null): PayrollPeriod
    {
        return DB::transaction(function () use ($year, $month, $warehouseId, $userId, $notes): PayrollPeriod {
            $existing = PayrollPeriod::where('year', $year)
                ->where('month', $month)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw new RuntimeException('Periode gaji '.$existing->label.' untuk cabang ini sudah ada ('.$existing->period_number.').');
            }

            $start = sprintf('%04d-%02d-01', $year, $month);
            $end = sprintf('%04d-%02d-%02d', $year, $month, (int) date('t', (int) strtotime($start)));

            return PayrollPeriod::create([
                'period_number' => $this->generatePeriodNumber($year, $month),
                'year' => $year,
                'month' => $month,
                'warehouse_id' => $warehouseId,
                'start_date' => $start,
                'end_date' => $end,
                'payment_date' => $end,
                'status' => PayrollPeriodStatus::Draft,
                'notes' => $notes,
                'created_by' => $userId,
            ]);
        });
    }

    /**
     * Hasilkan payroll untuk semua karyawan aktif di cabang periode.
     *
     * @param  array{working_days?: float, overtime_hours?: float, overtime_rate?: float, use_attendance?: bool}  $options
     * @return array{period: PayrollPeriod, created: int, skipped: int}
     *
     * @throws RuntimeException bila periode tidak ditemukan atau sudah digenerate.
     */
    public function generatePayrolls(PayrollPeriod $period, array $options = []): array
    {
        return DB::transaction(function () use ($period, $options): array {
            $period = PayrollPeriod::whereKey($period->getKey())->lockForUpdate()->firstOrFail();

            if ($period->status !== PayrollPeriodStatus::Draft) {
                throw new RuntimeException('Periode '.$period->label.' berstatus '.$period->status->label.' — tidak dapat digenerate ulang.');
            }

            $useAttendance = (bool) ($options['use_attendance'] ?? false);
            $defaultWorkingDays = (float) ($options['working_days'] ?? 22);
            $overtimeRate = (float) ($options['overtime_rate'] ?? 0);
            $created = 0;

            $employees = Employee::where('is_active', true)
                ->whereNull('resign_date')
                ->where(function ($query) use ($period): void {
                    $query->whereNull('warehouse_id')->orWhere('warehouse_id', $period->warehouse_id);
                })
                ->whereDoesntHave('payrolls', fn ($payroll) => $payroll->where('payroll_period_id', $period->id))
                ->orderBy('full_name')
                ->get();

            $components = PayrollComponent::where('is_active', true)->orderBy('type')->get();

            foreach ($employees as $employee) {
                // Ambil rekap absensi & lembur bila modul absensi diaktifkan
                // (hanya untuk karyawan di cabang periode tersebut).
                $workingDays = $defaultWorkingDays;
                $overtimeHours = (float) ($options['overtime_hours'] ?? 0);

                if ($useAttendance && $period->warehouse_id !== null && $employee->warehouse_id === $period->warehouse_id) {
                    $recap = app(AttendanceService::class)->recap(
                        $employee,
                        $period->start_date->toDateString(),
                        $period->end_date->toDateString(),
                    );

                    $presentDays = $recap['present'] + $recap['late'];
                    if ($presentDays > 0) {
                        $workingDays = (float) $presentDays;
                    }

                    $overtimeHours = $recap['overtime_hours'];
                }

                $payroll = $this->buildPayroll($period, $employee, $workingDays, $overtimeHours, $overtimeRate);

                if ($payroll === null) {
                    continue;
                }

                $created++;
                $this->attachComponentLines($payroll, $components);
                $this->recalculate($payroll);
            }

            $period->update(['status' => PayrollPeriodStatus::Generated]);

            return ['period' => $period->fresh(), 'created' => $created, 'skipped' => $employees->count() - $created];
        });
    }

    /**
     * Setujui periode (payroll terkunci, siap dibayar).
     *
     * @throws RuntimeException bila periode belum digenerate atau sudah disetujui.
     */
    public function approvePeriod(PayrollPeriod $period, int $userId): PayrollPeriod
    {
        return DB::transaction(function () use ($period, $userId): PayrollPeriod {
            $period = PayrollPeriod::whereKey($period->getKey())->lockForUpdate()->firstOrFail();

            if ($period->status !== PayrollPeriodStatus::Generated) {
                throw new RuntimeException('Periode '.$period->label.' harus berstatus "Dihasilkan" sebelum disetujui.');
            }

            $period->update([
                'status' => PayrollPeriodStatus::Approved,
                'approved_by' => $userId,
            ]);

            $period->payrolls()->where('status', PayrollStatus::Draft)->update([
                'status' => PayrollStatus::Approved,
            ]);

            return $period->fresh();
        });
    }

    /**
     * Bayar payroll karyawan: kas keluar + jurnal otomatis
     * Dr: 5210 Biaya Gaji / Cr: 1110 Kas Toko.
     *
     * @throws RuntimeException bila payroll tidak disetujui atau sudah dibayar.
     */
    public function payPayroll(Payroll $payroll, ?int $userId, ?string $method = 'transfer'): Payroll
    {
        return DB::transaction(function () use ($payroll, $userId, $method): Payroll {
            $payroll = Payroll::whereKey($payroll->getKey())->lockForUpdate()->firstOrFail();

            if ($payroll->status !== PayrollStatus::Approved) {
                throw new RuntimeException('Payroll '.$payroll->payroll_number.' harus disetujui terlebih dahulu.');
            }

            $net = (float) $payroll->net_salary;
            if ($net <= 0) {
                throw new RuntimeException('Gaji bersih payroll '.$payroll->payroll_number.' tidak lebih dari 0.');
            }

            $payroll->update([
                'status' => PayrollStatus::Paid,
                'paid_at' => now()->toDateString(),
            ]);

            $employee = $payroll->employee;

            CashTransaction::create([
                'transaction_number' => $this->generateCashNumber(),
                'type' => 'out',
                'category' => 'salary',
                'amount' => $net,
                'transaction_date' => now()->toDateString(),
                'description' => 'Pembayaran gaji '.$payroll->payroll_number.' — '.$employee?->full_name,
                'warehouse_id' => $payroll->warehouse_id,
                'payment_method' => $method,
                'reference_type' => 'payroll',
                'reference_id' => $payroll->id,
                'created_by' => $userId,
            ]);

            $this->postPayrollJournal($payroll, $net, $userId);

            $this->markPeriodPaid($payroll->payrollPeriod);

            return $payroll->fresh();
        });
    }

    /**
     * Hitung ulang total payroll dari baris komponen (dipakai setelah edit garis).
     */
    public function recalculate(Payroll $payroll): void
    {
        $earnings = (float) $payroll->componentLines()->where('type', PayrollComponentType::Earning)->sum('amount');
        $deductions = (float) $payroll->componentLines()->where('type', PayrollComponentType::Deduction)->sum('amount');
        $tax = (float) $payroll->componentLines()->where('type', PayrollComponentType::Tax)->sum('amount');

        $payroll->update([
            'total_earnings' => round($earnings, 2),
            'total_deductions' => round($deductions, 2),
            'tax_amount' => round($tax, 2),
            'net_salary' => round((float) $payroll->basic_salary + $earnings + (float) $payroll->overtime_amount - $deductions - $tax, 2),
        ]);
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    private function buildPayroll(PayrollPeriod $period, Employee $employee, float $workingDays, float $overtimeHours, float $overtimeRate): ?Payroll
    {
        $basic = (float) $employee->basic_salary;

        // Gaji harian proporsional dengan hari kerja.
        if ($employee->payment_type !== 'monthly' && $workingDays > 0) {
            $daily = $basic / 30;
            $basic = round($daily * $workingDays, 2);
        }

        $overtimeAmount = round($overtimeHours * $overtimeRate, 2);

        $payroll = Payroll::create([
            'payroll_number' => $this->generatePayrollNumber($period),
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'warehouse_id' => $period->warehouse_id,
            'basic_salary' => $basic,
            'total_earnings' => 0,
            'total_deductions' => 0,
            'tax_amount' => 0,
            'net_salary' => $basic + $overtimeAmount,
            'working_days' => $workingDays,
            'overtime_hours' => $overtimeHours,
            'overtime_amount' => $overtimeAmount,
            'status' => PayrollStatus::Draft,
            'created_by' => $period->created_by,
        ]);

        return $payroll;
    }

    /**
     * Kenakan komponen tetap aktif ke payroll baru.
     */
    private function attachComponentLines(Payroll $payroll, $components): void
    {
        foreach ($components as $component) {
            $amount = match ($component->calculation_type) {
                'fixed' => (float) $component->amount,
                'percentage_of_basic' => round((float) $payroll->basic_salary * ((float) $component->percentage / 100), 2),
                default => 0, // manual: diisi admin lewat UI
            };

            if ($amount <= 0) {
                continue;
            }

            PayrollComponentLine::create([
                'payroll_id' => $payroll->id,
                'payroll_component_id' => $component->id,
                'name' => $component->name,
                'type' => $component->type,
                'amount' => $amount,
            ]);
        }
    }

    /**
     * Jurnal otomatis gaji: Dr Biaya Gaji (5210) / Cr Kas Toko (1110).
     */
    private function postPayrollJournal(Payroll $payroll, float $amount, ?int $userId): void
    {
        $debit = Account::where('code', '5210')->first();  // Biaya Gaji
        $credit = Account::where('code', '1110')->first(); // Kas Toko

        if (! $debit || ! $credit) {
            return; // CoA belum di-seed — kas tetap tercatat, jurnal dilewati
        }

        $description = 'Jurnal gaji '.$payroll->payroll_number;

        $this->journalService->createJournal([
            'journal_date' => now()->toDateString(),
            'type' => 'general',
            'reference_type' => 'payroll',
            'reference_id' => (int) $payroll->id,
            'description' => $description,
            'created_by' => $userId,
            'lines' => [
                ['account_id' => $debit->id, 'type' => 'debit', 'amount' => $amount, 'description' => $description],
                ['account_id' => $credit->id, 'type' => 'credit', 'amount' => $amount, 'description' => $description],
            ],
        ]);
    }

    /**
     * Tandai periode "paid" bila semua payroll sudah dibayar.
     */
    private function markPeriodPaid(?PayrollPeriod $period): void
    {
        if (! $period) {
            return;
        }

        $unpaid = $period->payrolls()
            ->whereNotIn('status', [PayrollStatus::Paid, PayrollStatus::Cancelled])
            ->count();

        if ($unpaid === 0) {
            $period->update(['status' => PayrollPeriodStatus::Paid]);
        }
    }

    private function generatePeriodNumber(int $year, int $month): string
    {
        $prefix = 'PY-'.$year.str_pad((string) $month, 2, '0', STR_PAD_LEFT).'-';

        $latest = PayrollPeriod::where('period_number', 'like', $prefix.'%')
            ->orderByDesc('period_number')
            ->value('period_number');

        $sequence = 1;

        if ($latest !== null) {
            $sequence = (int) substr($latest, -4) + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function generatePayrollNumber(PayrollPeriod $period): string
    {
        $prefix = 'PR-'.$period->year.str_pad((string) $period->month, 2, '0', STR_PAD_LEFT).'-';

        $latest = Payroll::where('payroll_number', 'like', $prefix.'%')
            ->orderByDesc('payroll_number')
            ->value('payroll_number');

        $sequence = 1;

        if ($latest !== null) {
            $sequence = (int) substr($latest, -4) + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function generateCashNumber(): string
    {
        $prefix = 'CT-'.now()->format('Ymd').'-';

        $latest = CashTransaction::where('transaction_number', 'like', $prefix.'%')
            ->orderByDesc('transaction_number')
            ->value('transaction_number');

        $sequence = 1;

        if ($latest !== null) {
            $sequence = (int) substr($latest, -4) + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
