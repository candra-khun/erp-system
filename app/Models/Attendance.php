<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceStatus;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Data kehadiran harian karyawan — clock in/out + status otomatis.
 */
class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'attendance_number',
        'employee_id',
        'attendance_date',
        'work_shift_id',
        'clock_in',
        'clock_out',
        'status',
        'late_minutes',
        'early_out_minutes',
        'overtime_hours',
        'check_in_method',
        'check_out_method',
        'latitude',
        'longitude',
        'selfie_path',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
            'status' => AttendanceStatus::class,
            'late_minutes' => 'integer',
            'early_out_minutes' => 'integer',
            'overtime_hours' => 'decimal:2',
            'check_in_method' => AttendanceMethod::class,
            'check_out_method' => AttendanceMethod::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
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
     * @return HasMany<AttendanceValidation>
     */
    public function validations(): HasMany
    {
        return $this->hasMany(AttendanceValidation::class);
    }

    /**
     * Scope absensi karyawan di cabang yang dapat diakses user.
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

    /**
     * True bila absensi sudah lengkap (clock in & clock out).
     */
    public function getIsCompleteAttribute(): bool
    {
        return $this->clock_in !== null && $this->clock_out !== null;
    }
}
