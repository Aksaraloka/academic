<?php

namespace App\Enums;

enum InternshipType: string
{
    case Internship = 'internship';
    case IndependentStudy = 'independent_study';
    case Exchange = 'exchange';
    case VillageProject = 'village_project';
    case Research = 'research';
    case Entrepreneurship = 'entrepreneurship';
    case Teaching = 'teaching';

    public function label(): string
    {
        return match ($this) {
            self::Internship => 'Magang / praktik kerja',
            self::IndependentStudy => 'Studi independen',
            self::Exchange => 'Pertukaran pelajar',
            self::VillageProject => 'Proyek di desa / KKN tematik',
            self::Research => 'Penelitian / riset',
            self::Entrepreneurship => 'Kewirausahaan',
            self::Teaching => 'Asistensi mengajar',
        };
    }
}
