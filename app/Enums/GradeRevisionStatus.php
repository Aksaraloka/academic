<?php

namespace App\Enums;

enum GradeRevisionStatus: string
{
    case Pending = 'menunggu';
    case Approved = 'disetujui';
    case Rejected = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu persetujuan',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Pending => 'info',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'clock',
            self::Approved => 'check-circle',
            self::Rejected => 'x-circle',
        };
    }
}
