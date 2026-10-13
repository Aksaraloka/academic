<?php

namespace App\Enums;

enum FeedbackCategory: string
{
    case Academic = 'akademik';
    case Lecturer = 'dosen';
    case Facility = 'fasilitas';
    case Administration = 'administrasi';
    case Finance = 'keuangan';
    case Application = 'aplikasi';
    case Other = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::Academic => 'Akademik dan perkuliahan',
            self::Lecturer => 'Dosen dan pengajaran',
            self::Facility => 'Sarana dan prasarana',
            self::Administration => 'Layanan administrasi',
            self::Finance => 'Keuangan dan biaya',
            self::Application => 'Aplikasi Aksaraloka',
            self::Other => 'Lainnya',
        };
    }
}
