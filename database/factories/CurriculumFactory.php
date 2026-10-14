<?php

namespace Database\Factories;

use App\Models\Curriculum;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Curriculum>
 */
class CurriculumFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => 'Kurikulum 2022',
            'batch_from' => 2022,
            'is_active' => true,
        ];
    }
}
