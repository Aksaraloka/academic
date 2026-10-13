<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'hadir';
    case Late = 'terlambat';
    case Excused = 'izin';
    case Sick = 'sakit';
    case Absent = 'alpa';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Hadir',
            self::Late => 'Terlambat',
            self::Excused => 'Izin',
            self::Sick => 'Sakit',
            self::Absent => 'Alpa',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Present => 'success',
            self::Late => 'warning',
            self::Excused => 'info',
            self::Sick => 'warning',
            self::Absent => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Present => 'check-circle',
            self::Late => 'clock',
            self::Excused => 'document-text',
            self::Sick => 'heart',
            self::Absent => 'x-circle',
        };
    }

    /**
     * Status yang dipakai tiap jenis institusi (kampus tidak memakai Terlambat).
     *
     * @return array<int, self>
     */
    public static function forLevel(TenantLevel $level): array
    {
        return $level === TenantLevel::Campus
            ? [self::Present, self::Excused, self::Sick, self::Absent]
            : self::cases();
    }
}
