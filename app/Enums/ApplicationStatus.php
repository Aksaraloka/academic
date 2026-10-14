<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Submitted = 'submitted';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Menunggu seleksi',
            self::Accepted => 'Diterima',
            self::Rejected => 'Tidak diterima',
            self::Withdrawn => 'Dibatalkan',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Submitted => 'warning',
            self::Accepted => 'success',
            self::Rejected => 'danger',
            self::Withdrawn => 'neutral',
        };
    }
}
