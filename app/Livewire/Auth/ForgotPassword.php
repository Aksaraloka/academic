<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Lupa kata sandi')]
class ForgotPassword extends Component
{
    #[Validate('required|email', as: 'email')]
    public string $email = '';

    public bool $sent = false;

    public function sendLink(): void
    {
        $this->validate();

        $throttleKey = 'forgot-password|'.Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak permintaan. Coba lagi dalam '.RateLimiter::availableIn($throttleKey).' detik.',
            ]);
        }

        RateLimiter::hit($throttleKey, 300);

        // Hasil sengaja tidak ditampilkan agar tidak membocorkan email yang terdaftar.
        Password::sendResetLink(['email' => $this->email]);

        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.auth.forgot-password');
    }
}
