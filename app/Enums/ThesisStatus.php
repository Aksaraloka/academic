<?php

namespace App\Enums;

enum ThesisStatus: string
{
    case Proposed = 'proposed';
    case Rejected = 'rejected';
    case Supervised = 'supervised';
    case DefenseRequested = 'defense_requested';
    case DefenseScheduled = 'defense_scheduled';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Proposed => 'Judul diajukan',
            self::Rejected => 'Judul ditolak',
            self::Supervised => 'Dalam bimbingan',
            self::DefenseRequested => 'Menunggu jadwal sidang',
            self::DefenseScheduled => 'Sidang terjadwal',
            self::Completed => 'Lulus sidang',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Proposed => 'warning',
            self::Rejected => 'danger',
            self::Supervised => 'info',
            self::DefenseRequested => 'warning',
            self::DefenseScheduled => 'primary',
            self::Completed => 'success',
        };
    }

    /**
     * Tugas akhir yang masih berjalan (menghalangi pengajuan judul baru).
     */
    public function isActive(): bool
    {
        return in_array($this, [self::Proposed, self::Supervised, self::DefenseRequested, self::DefenseScheduled], true);
    }
}
