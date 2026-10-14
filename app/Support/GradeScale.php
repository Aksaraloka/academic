<?php

namespace App\Support;

use App\Models\TeachingAssignment;
use App\Models\Tenant;

/**
 * Aturan konversi nilai angka ke huruf mutu A–E dan bobot komponen, diambil dari pengaturan kampus.
 */
final class GradeScale
{
    /** Bobot angka mutu per huruf. */
    public const POINTS = ['A' => 4.0, 'B' => 3.0, 'C' => 2.0, 'D' => 1.0, 'E' => 0.0];

    public const COMPONENTS = ['assignment' => 'Tugas', 'midterm' => 'UTS', 'final' => 'UAS', 'attendance' => 'Kehadiran'];

    /**
     * @param  array<string, int>  $weights  assignment, midterm, final, attendance (jumlah 100)
     * @param  array<string, int>  $thresholds  batas minimal tiap huruf A–D; di bawah D adalah E
     * @param  array<int, string>  $remedialLetters  huruf yang berhak remedial
     */
    public function __construct(
        public readonly array $weights,
        public readonly array $thresholds,
        public readonly array $remedialLetters,
    ) {}

    public static function for(Tenant $tenant): self
    {
        return new self(
            weights: array_merge(Tenant::DEFAULT_SETTINGS['grade_weights'], (array) $tenant->setting('grade_weights')),
            thresholds: array_merge(Tenant::DEFAULT_SETTINGS['grade_scale'], (array) $tenant->setting('grade_scale')),
            remedialLetters: array_values((array) $tenant->setting('remedial_letters')),
        );
    }

    public function letterFor(?float $score): ?string
    {
        if ($score === null) {
            return null;
        }

        foreach (['A', 'B', 'C', 'D'] as $letter) {
            if ($score >= $this->thresholds[$letter]) {
                return $letter;
            }
        }

        return 'E';
    }

    public static function pointsFor(?string $letter): ?float
    {
        return $letter === null ? null : (self::POINTS[$letter] ?? null);
    }

    /**
     * Nilai akhir berbobot; null bila ada komponen yang belum terisi.
     *
     * @param  array{assignment: ?float, midterm: ?float, final: ?float, attendance: ?float}  $scores
     * @param  array<string, int|float>  $weights
     */
    public static function total(array $scores, array $weights): ?float
    {
        $sum = 0.0;

        foreach (array_keys(self::COMPONENTS) as $component) {
            if (($scores[$component] ?? null) === null) {
                return null;
            }

            $sum += $scores[$component] * ($weights[$component] ?? 0);
        }

        return round($sum / 100, 2);
    }

    /**
     * Bobot yang berlaku untuk penugasan: bobot khususnya bila lengkap, selain itu bobot bawaan kampus.
     *
     * @return array<string, int>
     */
    public function weightsFor(TeachingAssignment $assignment): array
    {
        $own = [
            'assignment' => $assignment->weight_assignment,
            'midterm' => $assignment->weight_midterm,
            'final' => $assignment->weight_final,
            'attendance' => $assignment->weight_attendance,
        ];

        return in_array(null, $own, true) ? $this->weights : array_map('intval', $own);
    }
}
