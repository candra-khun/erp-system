<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HolidayType;
use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kalender hari libur: nasional, agama, perusahaan, dan libur mingguan
 * (weekly off — mis. setiap Minggu).
 */
class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'holiday_date',
        'type',
        'is_recurring_annual',
        'weekly_day_of_week',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
            'type' => HolidayType::class,
            'is_recurring_annual' => 'boolean',
            'weekly_day_of_week' => 'integer',
        ];
    }
}
