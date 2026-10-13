<?php

namespace App\Enums;

enum FeedbackStatus: string
{
    case New = 'baru';
    case InProgress = 'diproses';
    case Resolved = 'selesai';
    case Closed = 'ditutup';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::InProgress => 'Diproses',
            self::Resolved => 'Selesai',
            self::Closed => 'Ditutup',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::New => 'info',
            self::InProgress => 'warning',
            self::Resolved => 'success',
            self::Closed => 'neutral',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::New => 'arrow-up-tray',
            self::InProgress => 'clock',
            self::Resolved => 'check-circle',
            self::Closed => 'x-circle',
        };
    }
}
