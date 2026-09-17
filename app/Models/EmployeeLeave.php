<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeaveStatus;
use Database\Factories\EmployeeLeaveFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pengajuan cuti/absensi karyawan — PRD Fase 4 #12.
 */
class EmployeeLeave extends Model
{
    /** @use HasFactory<EmployeeLeaveFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'leave_number',
        'employee_id',
        'type',
        'start_date',
        'end_date',
        'days',
        'status',
        'notes',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days' => 'decimal:2',
            'status' => LeaveStatus::class,
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
     * Scope cuti milik karyawan di cabang yang dapat diakses user.
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
