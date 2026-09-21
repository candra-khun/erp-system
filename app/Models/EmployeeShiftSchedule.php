<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ShiftScheduleSource;
use Database\Factories\EmployeeShiftScheduleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Penugasan shift per karyawan per tanggal (bisa berulang mingguan).
 */
class EmployeeShiftSchedule extends Model
{
    /** @use HasFactory<EmployeeShiftScheduleFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'work_shift_id',
        'effective_date',
        'end_date',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'end_date' => 'date',
            'source' => ShiftScheduleSource::class,
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
     * @return BelongsTo<WorkShift, $this>
     */
    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class);
    }

    /**
     * Scope penugasan shift karyawan di cabang yang dapat diakses user.
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
