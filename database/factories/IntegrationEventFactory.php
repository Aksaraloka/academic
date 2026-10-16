<?php

namespace Database\Factories;

use App\Models\IntegrationEvent;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntegrationEvent>
 */
class IntegrationEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'type' => 'class.updated',
            'payload' => ['class_id' => fake()->numberBetween(1, 500)],
            'attempts' => 0,
        ];
    }

    public function delivered(): static
    {
        return $this->state(['attempts' => 1, 'delivered_at' => now()]);
    }

    public function failed(string $error = 'HTTP 500'): static
    {
        return $this->state(['attempts' => 3, 'last_error' => $error]);
    }
}
