<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'nim' => (string) fake()->unique()->numerify('######'),
            'batch_year' => 2024,
            'birth_date' => fake()->dateTimeBetween('-16 years', '-12 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['L', 'P']),
            'status' => 'aktif',
            'entry_date' => today()->toDateString(),
        ];
    }
}
