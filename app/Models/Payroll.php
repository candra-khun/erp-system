<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PayrollStatus;
use Database\Factories\PayrollFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Penggajian per karyawan dalam satu periode — PRD Fase 4 #12.
 */
class Payroll extends Model
{
    /** @use HasFactory<PayrollFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'payroll_number',
        'payroll_period_id',
        'employee_id',
        'warehouse_id',
        'basic_salary',
        'total_earnings',
        'total_deductions',
        'tax_amount',
        'net_salary',
        'working_days',
        'overtime_hours',
        'overtime_amount',
        'status',
        'paid_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'total_earnings' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'working_days' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'overtime_amount' => 'decimal:2',
            'paid_at' => 'date',
            'status' => PayrollStatus::class,
        ];
    }

    /**
     * @return BelongsTo<PayrollPeriod, $this>
     */
    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return HasMany<PayrollComponentLine>
     */
    public function componentLines(): HasMany
    {
        return $this->hasMany(PayrollComponentLine::class);
    }

    /**
     * Scope ke cabang yang dapat diakses user (null = tanpa batasan).
     *
     * @param  ?list<int>  $warehouseIds
     */
    public function scopeAccessibleWarehouse(Builder $query, ?array $warehouseIds): Builder
    {
        if ($warehouseIds === null) {
            return $query;
        }

        return $query->whereIn('payrolls.warehouse_id', $warehouseIds);
    }
}
