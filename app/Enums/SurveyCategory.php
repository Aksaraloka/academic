<?php

namespace App\Enums;

enum SurveyCategory: string
{
    case Satisfaction = 'kepuasan';
    case LecturerEvaluation = 'evaluasi_dosen';
    case TracerStudy = 'tracer_study';
    case General = 'umum';

    public function label(): string
    {
        return match ($this) {
            self::Satisfaction => 'Kepuasan layanan',
            self::LecturerEvaluation => 'Evaluasi dosen',
            self::TracerStudy => 'Tracer study',
            self::General => 'Umum',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Satisfaction => 'success',
            self::LecturerEvaluation => 'primary',
            self::TracerStudy => 'info',
            self::General => 'neutral',
        };
    }
}
