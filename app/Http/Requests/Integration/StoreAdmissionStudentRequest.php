<?php

namespace App\Http\Requests\Integration;

use App\Enums\PaymentMethod;
use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Serah terima mahasiswa baru dari Admission (`POST /admissions/students`).
 */
class StoreAdmissionStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'admission_ref' => ['required', 'string', 'max:60'],
            'program_id' => ['required', 'integer', 'min:1'],
            'term_id' => ['required', 'integer', 'min:1'],
            'batch_year' => ['required', 'integer', 'between:1990,2100'],
            'admission_path' => ['required', 'string', Rule::in(Student::ADMISSION_PATHS)],
            'ukt_group' => ['nullable', 'integer', 'min:1', 'max:255'],
            'student' => ['required', 'array'],
            'student.name' => ['required', 'string', 'max:255'],
            'student.email' => ['required', 'email:rfc', 'max:255'],
            'student.phone' => ['nullable', 'string', 'max:30'],
            'student.nik' => ['nullable', 'string', 'max:32'],
            'student.birth_place' => ['nullable', 'string', 'max:100'],
            'student.birth_date' => ['nullable', 'date', 'before:today'],
            'student.gender' => ['nullable', Rule::in(['L', 'P'])],
            'student.religion' => ['nullable', 'string', 'max:50'],
            'student.nationality' => ['nullable', 'string', 'max:50'],
            'student.address' => ['nullable', 'string', 'max:500'],
            'student.city' => ['nullable', 'string', 'max:100'],
            'student.province' => ['nullable', 'string', 'max:100'],
            'student.postal_code' => ['nullable', 'string', 'max:10'],
            'student.previous_school' => ['nullable', 'string', 'max:255'],
            'initial_payment' => ['required', 'array'],
            'initial_payment.amount' => ['required', 'integer', 'min:1'],
            'initial_payment.paid_on' => ['required', 'date'],
            'initial_payment.method' => ['required', Rule::in(array_column(PaymentMethod::cases(), 'value'))],
            'initial_payment.reference' => ['required', 'string', 'max:100'],
        ];
    }
}
