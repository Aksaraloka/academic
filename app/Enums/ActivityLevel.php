<?php

namespace App\Enums;

enum ActivityLevel: string
{
    case Campus = 'campus';
    case Regional = 'regional';
    case National = 'national';
    case International = 'international';

    public function label(): string
    {
        return match ($this) {
            self::Campus => 'Kampus',
            self::Regional => 'Kota / provinsi',
            self::National => 'Nasional',
            self::International => 'Internasional',
        };
    }

    public function multiplier(): int
    {
        return match ($this) {
            self::Campus => 1,
            self::Regional => 2,
            self::National => 3,
            self::International => 4,
        };
    }
}
