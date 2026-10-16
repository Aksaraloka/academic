<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Password;

/**
 * Mengirim email tautan atur kata sandi ke akun baru (password broker bawaan). Akun serah terima dibuat dengan kata
 * sandi acak yang tidak diketahui siapa pun, jadi tautan ini satu-satunya jalan masuk pertama.
 */
class SendPasswordSetupLink implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $userId)
    {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $user = User::query()->find($this->userId);

        if ($user !== null) {
            Password::broker()->sendResetLink(['email' => $user->email]);
        }
    }
}
