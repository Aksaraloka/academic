<?php

namespace App\Enums;

enum ConversionSource: string
{
    case Internship = 'internship';
    case Certification = 'certification';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Internship => 'Magang / MBKM',
            self::Certification => 'Sertifikasi kompetensi',
            self::Transfer => 'Kuliah di kampus lain / pertukaran',
        };
    }
}
