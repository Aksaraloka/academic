<?php

namespace App\Http\Requests\Integration;

use App\Enums\PaymentMethod;
use App\Jobs\FetchAdmissionPhoto;
use App\Models\Student;
use App\Services\AdmissionHandoffService;
use App\Services\IntegrationApps;
use Closure;
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
     * URL foto hanya boleh menunjuk ke Admission yang terhubung (mencegah server mengambil alamat sembarang).
     */
    private function belongsToAdmission(string $attribute, mixed $value, Closure $fail): void
    {
        $admission = parse_url(app(IntegrationApps::class)->get(IntegrationApps::ADMISSION)->url());
        $target = is_string($value) ? parse_url($value) : false;

        if (! is_array($admission) || ! is_array($target) || ! isset($admission['host'], $target['host'])
            || strcasecmp($admission['host'], $target['host']) !== 0 || ($admission['port'] ?? null) !== ($target['port'] ?? null)) {
            $fail('URL foto harus berada di alamat Admission yang terhubung.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $paysSelf = $this->input('funding', AdmissionHandoffService::FUNDING_SELF) !== AdmissionHandoffService::FUNDING_KIPK;

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
            'funding' => ['nullable', Rule::in(AdmissionHandoffService::FUNDINGS), function (string $attribute, mixed $value, Closure $fail): void {
                if ($value === AdmissionHandoffService::FUNDING_KIPK && $this->input('admission_path') !== 'KIP-K') {
                    $fail('Pendanaan kip_k hanya sah untuk jalur masuk KIP-K.');
                }
            }],
            'initial_payment' => [Rule::requiredIf($paysSelf), 'nullable', 'array'],
            'initial_payment.amount' => [Rule::requiredIf($paysSelf), 'integer', 'min:1'],
            'initial_payment.paid_on' => [Rule::requiredIf($paysSelf), 'date'],
            'initial_payment.method' => [Rule::requiredIf($paysSelf), Rule::in(array_values(array_diff(array_column(PaymentMethod::cases(), 'value'), [PaymentMethod::Scholarship->value])))],
            'initial_payment.reference' => [Rule::requiredIf($paysSelf), 'string', 'max:100'],
            'guardians' => ['nullable', 'array', 'max:3'],
            'guardians.*.relation' => ['required', Rule::in(array_keys(AdmissionHandoffService::GUARDIAN_RELATIONS)), 'distinct'],
            'guardians.*.name' => ['required', 'string', 'max:255'],
            'guardians.*.phone' => ['nullable', 'string', 'max:30'],
            'guardians.*.occupation' => ['nullable', 'string', 'max:100'],
            'guardians.*.education' => ['nullable', 'string', 'max:100'],
            'guardians.*.address' => ['nullable', 'string', 'max:500'],
            'photo' => ['nullable', 'array'],
            'photo.url' => ['required_with:photo', 'string', 'url:http,https', 'max:2000', $this->belongsToAdmission(...)],
            'photo.sha256' => ['required_with:photo', 'string', 'regex:/^[A-Fa-f0-9]{64}$/'],
            'photo.mime' => ['required_with:photo', Rule::in(array_keys(FetchAdmissionPhoto::EXTENSIONS))],
        ];
    }
}
