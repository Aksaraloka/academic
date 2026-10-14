<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $url = route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]);

            return (new MailMessage)
                ->subject('Atur ulang kata sandi Aksaraloka')
                ->greeting('Halo, '.$notifiable->name)
                ->line('Kami menerima permintaan untuk mengatur ulang kata sandi akunmu.')
                ->action('Atur ulang kata sandi', $url)
                ->line('Tautan berlaku '.config('auth.passwords.users.expire').' menit. Abaikan email ini bila kamu tidak memintanya.');
        });
    }
}
