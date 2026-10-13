<?php

namespace App\Enums;

enum SurveyQuestionType: string
{
    case Rating = 'rating';
    case Single = 'pilihan';
    case Multiple = 'pilihan_ganda';
    case Text = 'teks';

    public function label(): string
    {
        return match ($this) {
            self::Rating => 'Skala 1–5',
            self::Single => 'Pilih satu',
            self::Multiple => 'Pilih beberapa',
            self::Text => 'Jawaban teks',
        };
    }

    public function hasOptions(): bool
    {
        return in_array($this, [self::Single, self::Multiple], true);
    }
}
