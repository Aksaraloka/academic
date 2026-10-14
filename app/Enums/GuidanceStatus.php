<?php

namespace App\Enums;

enum GuidanceStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Revise = 'revise';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu verifikasi',
            self::Verified => 'Terverifikasi',
            self::Revise => 'Perlu diperbaiki',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Verified => 'success',
            self::Revise => 'danger',
        };
    }
}
