<?php

namespace Database\Factories;

use App\Models\AcademicTerm;
use App\Models\StatusRequest;
use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatusRequest>
 */
class StatusRequestFactory extends Factory
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
            'type' => 'leave',
            'reason' => 'Ada kebutuhan keluarga yang harus diselesaikan semester ini.',
            'status' => 'pending',
        ];
    }
}
