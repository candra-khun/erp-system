<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OvertimeRequestStatus;
use Database\Factories\OvertimeRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pengajuan lembur karyawan — perlu approval atasan, jamnya masuk payroll.
 */
class OvertimeRequest extends Model
{
    /** @use HasFactory<OvertimeRequestFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'overtime_number',
        'employee_id',
        'overtime_date',
        'start_time',
        'end_time',
        'hours',
        'description',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'overtime_date' => 'date',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'hours' => 'decimal:2',
            'status' => OvertimeRequestStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope pengajuan lembur karyawan di cabang yang dapat diakses user.
     *
     * @param  ?list<int>  $warehouseIds
     */
    public function scopeAccessibleWarehouse(Builder $query, ?array $warehouseIds): Builder
    {
        if ($warehouseIds === null) {
            return $query;
        }

        return $query->whereHas('employee', fn ($employee) => $employee->whereIn('employees.warehouse_id', $warehouseIds));
    }
}
