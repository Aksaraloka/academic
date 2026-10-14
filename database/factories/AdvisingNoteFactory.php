<?php

namespace Database\Factories;

use App\Models\AcademicTerm;
use App\Models\AdvisingNote;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdvisingNote>
 */
class AdvisingNoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'student_id' => Student::factory(),
            'teacher_id' => Teacher::factory(),
            'academic_term_id' => AcademicTerm::factory(),
            'topic' => 'Rencana studi semester ini',
            'notes' => 'Membahas beban SKS dan target IPS.',
            'held_on' => '2026-09-10',
        ];
    }
}
