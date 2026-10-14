<?php

namespace App\Enums;

enum SubjectType: string
{
    case Required = 'required';
    case Elective = 'elective';

    public function label(): string
    {
        return match ($this) {
            self::Required => 'Wajib',
            self::Elective => 'Pilihan',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Required => 'primary',
            self::Elective => 'info',
        };
    }
}
