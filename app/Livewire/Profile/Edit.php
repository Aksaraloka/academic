<?php

namespace App\Livewire\Profile;

use App\Models\Student;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Ubah profil')]
class Edit extends Component
{
    public string $phone = '';

    public bool $is_domicile_same = true;

    public string $residence_type = '';

    public string $domicile_address = '';

    public string $domicile_city = '';

    public string $domicile_province = '';

    public string $domicile_postal_code = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = auth()->user();
        $student = $user->student;

        $this->phone = (string) ($student?->phone ?? $user->phone);

        if ($student) {
            $this->fill([
                'is_domicile_same' => $student->is_domicile_same,
                'residence_type' => (string) $student->residence_type,
                'domicile_address' => (string) $student->domicile_address,
                'domicile_city' => (string) $student->domicile_city,
                'domicile_province' => (string) $student->domicile_province,
                'domicile_postal_code' => (string) $student->domicile_postal_code,
            ]);
        }
    }

    /**
     * Mahasiswa mengubah telepon dan alamat domisili (mis. pindah kos). Alamat KTP, identitas, dan data akademik
     * dikelola BAAK.
     */
    public function saveContact(AuditLogger $audit): void
    {
        $user = auth()->user();
        $student = $user->student;

        $rules = ['phone' => ['nullable', 'string', 'max:30']];

        if ($student) {
            $rules += [
                'is_domicile_same' => ['boolean'],
                'residence_type' => ['nullable', Rule::in(Student::RESIDENCE_TYPES)],
                'domicile_address' => [Rule::requiredIf(! $this->is_domicile_same), 'nullable', 'string', 'max:500'],
                'domicile_city' => [Rule::requiredIf(! $this->is_domicile_same), 'nullable', 'string', 'max:100'],
                'domicile_province' => ['nullable', 'string', 'max:100'],
                'domicile_postal_code' => ['nullable', 'digits_between:4,10'],
            ];
        }

        $data = collect($this->validate($rules, [], [
            'phone' => 'telepon', 'residence_type' => 'jenis tempat tinggal', 'domicile_address' => 'alamat domisili',
            'domicile_city' => 'kota/kabupaten', 'domicile_province' => 'provinsi', 'domicile_postal_code' => 'kode pos',
        ]))->map(fn ($value) => $value === '' ? null : $value);

        $user->update(['phone' => $data['phone']]);

        if ($student) {
            $attributes = $data->all();

            if ($attributes['is_domicile_same']) {
                $attributes = array_merge($attributes, ['domicile_address' => null, 'domicile_city' => null, 'domicile_province' => null, 'domicile_postal_code' => null]);
            }

            $student->update($attributes);
            $audit->logChanges('student.contact_updated', $student);
        }

        $this->dispatch('toast', message: 'Kontak dan alamat diperbarui.', type: 'success');
    }

    public function savePassword(): void
    {
        $key = 'password-change:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'current_password' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.',
            ]);
        }

        $this->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ], [], ['current_password' => 'kata sandi saat ini', 'password' => 'kata sandi baru']);

        if (! Hash::check($this->current_password, auth()->user()->password)) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['current_password' => 'Kata sandi saat ini salah.']);
        }

        RateLimiter::clear($key);
        auth()->user()->update(['password' => $this->password]);

        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->dispatch('toast', message: 'Kata sandi diperbarui.', type: 'success');
    }

    public function render()
    {
        $student = auth()->user()->student;

        return view('livewire.profile.edit', [
            'student' => $student,
            'residenceTypes' => Student::RESIDENCE_TYPES,
        ]);
    }
}
