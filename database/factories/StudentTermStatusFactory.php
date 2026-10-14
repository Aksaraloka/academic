<?php

namespace Database\Factories;

use App\Models\AcademicTerm;
use App\Models\Student;
use App\Models\StudentTermStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentTermStatus>
 */
class StudentTermStatusFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'student_id' => Student::factory(),
            'academic_term_id' => AcademicTerm::factory(),
            'status' => 'cuti',
            'reason' => 'Alasan keluarga.',
        ];
    }
}
