<?php

namespace Database\Factories;

use App\Models\AcademicTerm;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
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
            'number' => 'INV-'.fake()->unique()->numerify('########'),
            'title' => 'Tagihan semester',
            'total' => 5000000,
            'status' => 'unpaid',
            'due_on' => today()->addDays(14),
            'issued_at' => now(),
        ];
    }
}
