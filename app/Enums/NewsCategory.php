<?php

namespace App\Enums;

enum NewsCategory: string
{
    case Announcement = 'pengumuman';
    case Program = 'program';
    case Scholarship = 'beasiswa';
    case Academic = 'akademik';
    case Event = 'kegiatan';
    case Achievement = 'prestasi';
    case Graduation = 'wisuda';

    public function label(): string
    {
        return match ($this) {
            self::Announcement => 'Pengumuman',
            self::Program => 'Program baru',
            self::Scholarship => 'Beasiswa',
            self::Academic => 'Akademik',
            self::Event => 'Kegiatan',
            self::Achievement => 'Prestasi',
            self::Graduation => 'Wisuda',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Announcement => 'neutral',
            self::Program => 'info',
            self::Scholarship => 'success',
            self::Academic => 'primary',
            self::Event => 'warning',
            self::Achievement => 'success',
            self::Graduation => 'primary',
        };
    }
}
