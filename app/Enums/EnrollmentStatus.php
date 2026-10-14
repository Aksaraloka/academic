<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Submitted => 'Menunggu persetujuan',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Submitted => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }

    /**
     * Mahasiswa hanya bisa mengubah pilihan saat draf atau setelah ditolak.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
    }
}
