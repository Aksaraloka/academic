<?php

namespace App\Enums;

enum AttendanceMode: string
{
    case Manual = 'manual';
    case Daily = 'daily';
    case Gate = 'gate';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Daily => 'Harian',
            self::Gate => 'Gerbang',
        };
    }
}
