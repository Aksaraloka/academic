<?php

namespace App\Enums;

enum TenantLevel: string
{
    case School = 'school';
    case Campus = 'campus';

    public function label(): string
    {
        return match ($this) {
            self::School => 'Kampus',
            self::Campus => 'Kampus',
        };
    }
}
