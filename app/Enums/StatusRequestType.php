<?php

namespace App\Enums;

enum StatusRequestType: string
{
    case Leave = 'leave';
    case Reactivation = 'reactivation';

    public function label(): string
    {
        return match ($this) {
            self::Leave => 'Cuti akademik',
            self::Reactivation => 'Aktif kembali',
        };
    }
}
