<?php

namespace Database\Factories;

use App\Models\AcademicTerm;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicTerm>
 */
class AcademicTermFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => '2026/2027',
            'semester' => 'Ganjil',
            'starts_at' => '2026-07-13',
            'ends_at' => '2026-12-19',
            'is_active' => true,
            'is_locked' => false,
        ];
    }
}
