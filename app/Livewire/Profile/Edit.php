<?php

namespace App\Livewire\Profile;

use App\Services\AuditLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Ubah profil')]
class Edit extends Component
{
    public string $phone = '';

    public string $address = '';

    public string $city = '';

    public string $province = '';

    public string $postal_code = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = auth()->user();
        $student = $user->student;

        $this->phone = (string) ($student?->phone ?? $user->phone);

        if ($student) {
            $this->address = (string) $student->address;
            $this->city = (string) $student->city;
            $this->province = (string) $student->province;
            $this->postal_code = (string) $student->postal_code;
        }
    }

    /**
     * Mahasiswa hanya mengubah kontak dan alamat; identitas dan data akademik dikelola BAAK.
     */
    public function saveContact(AuditLogger $audit): void
    {
        $user = auth()->user();
        $student = $user->student;

        $rules = ['phone' => ['nullable', 'string', 'max:30']];

        if ($student) {
            $rules += [
                'address' => ['nullable', 'string', 'max:500'],
                'city' => ['nullable', 'string', 'max:100'],
                'province' => ['nullable', 'string', 'max:100'],
                'postal_code' => ['nullable', 'digits_between:4,10'],
            ];
        }

        $data = collect($this->validate($rules, [], [
            'phone' => 'telepon', 'address' => 'alamat', 'city' => 'kota/kabupaten', 'province' => 'provinsi', 'postal_code' => 'kode pos',
        ]))->map(fn ($value) => $value === '' ? null : $value);

        $user->update(['phone' => $data['phone']]);

        if ($student) {
            $student->update($data->all());
            $audit->logChanges('student.contact_updated', $student);
        }

        $this->dispatch('toast', message: 'Kontak diperbarui.', type: 'success');
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
        return view('livewire.profile.edit', ['isStudent' => auth()->user()->student !== null]);
    }
}
