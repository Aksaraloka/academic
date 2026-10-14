<?php

namespace App\Enums;

enum ExamType: string
{
    case Midterm = 'midterm';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Midterm => 'UTS',
            self::Final => 'UAS',
        };
    }

    public function fullLabel(): string
    {
        return match ($this) {
            self::Midterm => 'Ujian Tengah Semester (UTS)',
            self::Final => 'Ujian Akhir Semester (UAS)',
        };
    }

    /**
     * Jenis agenda kalender akademik yang membatasi tanggal ujian.
     */
    public function eventType(): AcademicEventType
    {
        return match ($this) {
            self::Midterm => AcademicEventType::Midterm,
            self::Final => AcademicEventType::Final,
        };
    }
}
