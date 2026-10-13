<?php

namespace Database\Factories;

use App\Models\AcademicTerm;
use App\Models\StudyGroup;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudyGroup>
 */
class StudyGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'academic_term_id' => fn (array $attributes) => AcademicTerm::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'name' => fake()->randomElement(['7A', '7B', '8A', '9C']),
        ];
    }
}
