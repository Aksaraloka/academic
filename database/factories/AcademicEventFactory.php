<?php

namespace Database\Factories;

use App\Enums\AcademicEventType;
use App\Models\AcademicEvent;
use App\Models\AcademicTerm;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicEvent>
 */
class AcademicEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'academic_term_id' => AcademicTerm::factory(),
            'type' => AcademicEventType::Midterm,
            'title' => null,
            'starts_on' => '2026-10-19',
            'ends_on' => '2026-10-24',
            'notes' => null,
        ];
    }
}
