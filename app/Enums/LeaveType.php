<?php

namespace App\Enums;

enum LeaveType: string
{
    case Excused = 'izin';
    case Sick = 'sakit';
    case Duty = 'dinas';
    case Leave = 'cuti';

    public function label(): string
    {
        return match ($this) {
            self::Excused => 'Izin',
            self::Sick => 'Sakit',
            self::Duty => 'Dinas luar',
            self::Leave => 'Cuti',
        };
    }
}
