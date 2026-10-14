<?php

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Models\AcademicTerm;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'academic_term_id' => AcademicTerm::factory(),
            'student_id' => Student::factory(),
            'status' => EnrollmentStatus::Draft,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => ['status' => EnrollmentStatus::Approved, 'submitted_at' => now(), 'decided_at' => now()]);
    }
}
