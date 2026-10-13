<?php

namespace App\Enums;

enum AttendanceMode: string
{
    case Manual = 'manual';
    case Qr = 'qr';
    case Daily = 'daily';
    case Gate = 'gate';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Qr => 'QR dinamis',
            self::Daily => 'Harian',
            self::Gate => 'Gerbang',
        };
    }
}
