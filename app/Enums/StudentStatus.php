<?php

namespace App\Enums;

enum StudentStatus: string
{
    case Active = 'aktif';
    case Leave = 'cuti';
    case Inactive = 'nonaktif';
    case Transferred = 'mutasi';
    case Graduated = 'lulus';
    case Dropped = 'keluar';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Leave => 'Cuti akademik',
            self::Inactive => 'Non-aktif',
            self::Transferred => 'Mutasi',
            self::Graduated => 'Lulus',
            self::Dropped => 'Drop out (DO)',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Leave => 'warning',
            self::Inactive => 'neutral',
            self::Transferred => 'info',
            self::Graduated => 'primary',
            self::Dropped => 'danger',
        };
    }

    /**
     * Status yang masih bisa kembali aktif lewat pengajuan.
     */
    public function canReactivate(): bool
    {
        return in_array($this, [self::Leave, self::Inactive], true);
    }
}
