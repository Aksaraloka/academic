<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Universitas '.fake()->company(),
            'subdomain' => fake()->unique()->slug(2),
            'level' => 'campus',
            'timezone' => 'Asia/Jakarta',
            'settings' => null,
        ];
    }
}
