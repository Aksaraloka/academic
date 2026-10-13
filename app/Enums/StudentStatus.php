<?php

namespace App\Enums;

enum StudentStatus: string
{
    case Active = 'aktif';
    case Transferred = 'mutasi';
    case Graduated = 'lulus';
    case Dropped = 'keluar';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Transferred => 'Mutasi',
            self::Graduated => 'Lulus',
            self::Dropped => 'Keluar',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Transferred => 'info',
            self::Graduated => 'primary',
            self::Dropped => 'neutral',
        };
    }
}
