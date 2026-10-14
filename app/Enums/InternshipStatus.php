<?php

namespace App\Enums;

enum InternshipStatus: string
{
    case Submitted = 'submitted';
    case Rejected = 'rejected';
    case Approved = 'approved';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Menunggu persetujuan',
            self::Rejected => 'Ditolak',
            self::Approved => 'Disetujui / berjalan',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Submitted => 'warning',
            self::Rejected => 'danger',
            self::Approved => 'info',
            self::Completed => 'success',
            self::Cancelled => 'neutral',
        };
    }
}
