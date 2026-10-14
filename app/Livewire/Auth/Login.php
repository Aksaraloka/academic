<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Masuk')]
class Login extends Component
{
    #[Validate('required|email', as: 'email')]
    public string $email = '';

    #[Validate('required', as: 'kata sandi')]
    public string $password = '';

    public bool $remember = false;

    /** Pintu masuk yang dipilih: student (mahasiswa), guardian (orang tua), atau lecturer (dosen dan staf). */
    #[Url(as: 'as')]
    public string $portal = 'student';

    public function mount(): void
    {
        if (! in_array($this->portal, ['student', 'guardian', 'lecturer'], true)) {
            $this->portal = 'student';
        }
    }

    public function choosePortal(string $portal): void
    {
        if (in_array($portal, ['student', 'guardian', 'lecturer'], true)) {
            $this->portal = $portal;
            $this->resetErrorBag();
        }
    }

    public function login(): void
    {
        $this->validate();

        $throttleKey = Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($throttleKey).' detik.',
            ]);
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages(['email' => 'Email atau kata sandi salah.']);
        }

        $userPortal = Auth::user()->role->loginPortal();

        if ($userPortal !== 'any' && $userPortal !== $this->portal) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Akun ini milik '.match ($userPortal) {
                    'student' => 'mahasiswa. Pilih "Mahasiswa" di atas.',
                    'guardian' => 'orang tua. Pilih "Orang tua" di atas.',
                    default => 'dosen atau staf. Pilih "Dosen" di atas.',
                },
            ]);
        }

        RateLimiter::clear($throttleKey);
        session()->regenerate();

        $this->redirectIntended(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
