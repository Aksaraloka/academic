<?php

namespace App\Enums;

enum ActivityCategory: string
{
    case Organization = 'organization';
    case Committee = 'committee';
    case Achievement = 'achievement';
    case Certification = 'certification';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Organization => 'Organisasi',
            self::Committee => 'Kepanitiaan',
            self::Achievement => 'Prestasi dan penghargaan',
            self::Certification => 'Sertifikat dan pelatihan',
            self::Service => 'Pengabdian masyarakat',
        };
    }

    public function roleLabel(): string
    {
        return match ($this) {
            self::Organization, self::Committee => 'Jabatan',
            self::Achievement => 'Capaian (mis. Juara 1)',
            self::Certification => 'Kompetensi yang diperoleh',
            self::Service => 'Peran',
        };
    }

    /**
     * Poin dasar untuk usulan poin SKPI; tingkat kegiatan mengalikannya.
     */
    public function basePoints(): int
    {
        return match ($this) {
            self::Organization => 3,
            self::Committee => 2,
            self::Achievement => 5,
            self::Certification => 3,
            self::Service => 3,
        };
    }
}
