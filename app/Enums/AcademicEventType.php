<?php

namespace App\Enums;

enum AcademicEventType: string
{
    case Enrollment = 'enrollment';
    case Lecture = 'lecture';
    case Midterm = 'midterm';
    case Final = 'final';
    case GradingDeadline = 'grading_deadline';
    case Leave = 'leave';
    case Holiday = 'holiday';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Enrollment => 'Masa KRS',
            self::Lecture => 'Perkuliahan',
            self::Midterm => 'UTS',
            self::Final => 'UAS',
            self::GradingDeadline => 'Batas input nilai',
            self::Leave => 'Cuti akademik',
            self::Holiday => 'Libur',
            self::Other => 'Lainnya',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Enrollment => 'info',
            self::Lecture => 'success',
            self::Midterm, self::Final => 'warning',
            self::GradingDeadline => 'danger',
            self::Leave => 'primary',
            self::Holiday, self::Other => 'neutral',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Enrollment => 'pencil-square',
            self::Lecture => 'academic-cap',
            self::Midterm, self::Final => 'clipboard-document-check',
            self::GradingDeadline => 'check-badge',
            self::Leave => 'lock-closed',
            self::Holiday => 'sun',
            self::Other => 'calendar-days',
        };
    }

    /**
     * Jenis yang disimpan sebagai agenda; perkuliahan dan batas input nilai diturunkan dari data semester.
     *
     * @return array<int, self>
     */
    public static function storable(): array
    {
        return [self::Enrollment, self::Midterm, self::Final, self::Leave, self::Holiday, self::Other];
    }
}
