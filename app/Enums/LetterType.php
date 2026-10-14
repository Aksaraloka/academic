<?php

namespace App\Enums;

enum LetterType: string
{
    case ActiveStudent = 'active_student';
    case ProvisionalTranscript = 'transcript';
    case Recommendation = 'recommendation';
    case ResearchPermit = 'research_permit';

    public function label(): string
    {
        return match ($this) {
            self::ActiveStudent => 'Surat keterangan aktif kuliah',
            self::ProvisionalTranscript => 'Transkrip sementara',
            self::Recommendation => 'Surat rekomendasi',
            self::ResearchPermit => 'Surat izin penelitian',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ActiveStudent => 'Bukti status aktif untuk beasiswa, BPJS, atau administrasi lain. Terbit otomatis.',
            self::ProvisionalTranscript => 'Daftar nilai dan IPK saat ini. Terbit otomatis.',
            self::Recommendation => 'Rekomendasi dari program studi untuk beasiswa, magang, atau studi lanjut. Perlu persetujuan staf.',
            self::ResearchPermit => 'Pengantar izin penelitian ke instansi tujuan. Perlu persetujuan staf.',
        };
    }

    /**
     * Singkatan jenis surat pada nomor surat.
     */
    public function code(): string
    {
        return match ($this) {
            self::ActiveStudent => 'SKAK',
            self::ProvisionalTranscript => 'TRS',
            self::Recommendation => 'REK',
            self::ResearchPermit => 'IP',
        };
    }

    /**
     * Jenis yang tidak perlu persetujuan terbit otomatis saat diajukan.
     */
    public function requiresApproval(): bool
    {
        return in_array($this, [self::Recommendation, self::ResearchPermit], true);
    }

    public function purposeLabel(): string
    {
        return match ($this) {
            self::ActiveStudent => 'Keperluan surat',
            self::ProvisionalTranscript => 'Keperluan transkrip',
            self::Recommendation => 'Rekomendasi untuk',
            self::ResearchPermit => 'Judul / topik penelitian',
        };
    }
}
