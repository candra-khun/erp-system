<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WorkShiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Definisi shift kerja (pagi/siang/malam atau jam reguler kantor).
 */
class WorkShift extends Model
{
    /** @use HasFactory<WorkShiftFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'late_tolerance_minutes',
        'early_leave_tolerance_minutes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'late_tolerance_minutes' => 'integer',
            'early_leave_tolerance_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<EmployeeShiftSchedule>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(EmployeeShiftSchedule::class);
    }

    /**
     * @return HasMany<Attendance>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * @return HasMany<Employee>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Rentang jam shift sebagai label (mis. "08:00 - 16:00").
     */
    public function getTimeRangeAttribute(): string
    {
        return $this->start_time->format('H:i').' - '.$this->end_time->format('H:i');
    }
}
