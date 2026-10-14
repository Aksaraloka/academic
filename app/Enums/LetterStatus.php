<?php

namespace App\Enums;

enum LetterStatus: string
{
    case Submitted = 'submitted';
    case Issued = 'issued';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Menunggu persetujuan',
            self::Issued => 'Diterbitkan',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Submitted => 'warning',
            self::Issued => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'neutral',
        };
    }
}
