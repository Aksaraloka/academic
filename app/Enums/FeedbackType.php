<?php

namespace App\Enums;

enum FeedbackType: string
{
    case Suggestion = 'masukan';
    case Complaint = 'komplain';

    public function label(): string
    {
        return match ($this) {
            self::Suggestion => 'Masukan',
            self::Complaint => 'Komplain',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Suggestion => 'info',
            self::Complaint => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Suggestion => 'chat-bubble-left-right',
            self::Complaint => 'exclamation-triangle',
        };
    }
}
