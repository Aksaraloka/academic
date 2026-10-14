<?php

namespace App\Enums;

enum DefenseResult: string
{
    case Passed = 'passed';
    case PassedRevision = 'passed_revision';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Passed => 'Lulus',
            self::PassedRevision => 'Lulus dengan revisi',
            self::Failed => 'Tidak lulus',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Passed => 'success',
            self::PassedRevision => 'info',
            self::Failed => 'danger',
        };
    }

    public function isPass(): bool
    {
        return $this !== self::Failed;
    }
}
