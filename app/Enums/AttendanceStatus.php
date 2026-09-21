<?php

declare(strict_types=1);

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case Absent = 'absent';
    case OnLeave = 'on_leave';
    case Holiday = 'holiday';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Hadir',
            self::Late => 'Terlambat',
            self::Absent => 'Alpha',
            self::OnLeave => 'Izin/Cuti',
            self::Holiday => 'Libur',
        };
    }

    /**
     * Warna badge Tailwind untuk status absensi.
     */
    public function color(): string
    {
        return match ($this) {
            self::Present => 'green',
            self::Late => 'amber',
            self::Absent => 'red',
            self::OnLeave => 'blue',
            self::Holiday => 'gray',
        };
    }
}
