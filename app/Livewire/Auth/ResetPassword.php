<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Atur ulang kata sandi')]
class ResetPassword extends Component
{
    #[Locked]
    public string $token = '';

    #[Url]
    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
    }

    public function resetPassword(): void
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ], [], ['email' => 'email', 'password' => 'kata sandi']);

        $status = Password::reset(
            ['email' => $this->email, 'password' => $this->password, 'password_confirmation' => $this->password_confirmation, 'token' => $this->token],
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'Tautan tidak valid atau sudah kedaluwarsa. Minta tautan baru.']);
        }

        session()->flash('status', 'Kata sandi berhasil diubah. Silakan masuk dengan kata sandi baru.');

        $this->redirect(route('login'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.reset-password');
    }
}
