<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AttendanceValidationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bukti verifikasi absensi dari device: fingerprint, GPS, selfie, PIN, barcode.
 *
 * Disimpan per event (direction in/out) — interface terbuka untuk mesin absensi
 * atau aplikasi mobile yang mengirim hasil verifikasinya.
 */
class AttendanceValidation extends Model
{
    /** @use HasFactory<AttendanceValidationFactory> */
    use HasFactory;

    protected $fillable = [
        'attendance_id',
        'method',
        'device_id',
        'direction',
        'validated_at',
        'latitude',
        'longitude',
        'location_name',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'validated_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Attendance, $this>
     */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }
}
