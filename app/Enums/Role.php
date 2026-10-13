<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Principal = 'principal';
    case Curriculum = 'curriculum';
    case Teacher = 'teacher';
    case Duty = 'duty';
    case Student = 'student';
    case Guardian = 'guardian';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin / BAAK',
            self::Principal => 'Kaprodi / Pimpinan',
            self::Curriculum => 'Wakil dekan akademik',
            self::Teacher => 'Dosen',
            self::Duty => 'Petugas presensi',
            self::Student => 'Mahasiswa',
            self::Guardian => 'Orang tua',
        };
    }

    public function isStaff(): bool
    {
        return in_array($this, [self::Admin, self::Principal, self::Curriculum, self::Teacher, self::Duty], true);
    }

    public function isTeaching(): bool
    {
        return in_array($this, [self::Teacher, self::Curriculum, self::Principal, self::Duty], true);
    }
}
