<?php

namespace App\Enums;

enum GradeKind: string
{
    case Regular = 'reguler';
    case Remedial = 'remedial';

    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Reguler',
            self::Remedial => 'Remedial',
        };
    }
}
