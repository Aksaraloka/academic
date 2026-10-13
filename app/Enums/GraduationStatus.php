<?php

namespace App\Enums;

enum GraduationStatus: string
{
    case Submitted = 'diajukan';
    case Approved = 'disetujui';
    case Rejected = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Menunggu verifikasi',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Submitted => 'info',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Submitted => 'arrow-up-tray',
            self::Approved => 'check-circle',
            self::Rejected => 'arrow-path',
        };
    }
}
