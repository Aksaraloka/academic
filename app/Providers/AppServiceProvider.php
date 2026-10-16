<?php

namespace App\Providers;

use App\Models\AcademicTerm;
use App\Models\OauthClient;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\StudyGroupMember;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Observers\IntegrationEventObserver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Passport::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePassport();
        $this->configureIntegrationRateLimit();
        $this->observeIntegrationModels();

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

    /**
     * Model yang perubahannya diteruskan ke LMS lewat webhook (outbox). Tidak berbuat apa-apa bila integrasi mati.
     */
    private function observeIntegrationModels(): void
    {
        foreach ([TeachingAssignment::class, AcademicTerm::class, StudyGroupMember::class, StudyGroup::class, Subject::class, Student::class, Teacher::class, User::class] as $model) {
            $model::observe(IntegrationEventObserver::class);
        }
    }

    /**
     * Rute bawaan Passport dimatikan di `register()`; rute OAuth didaftarkan sendiri di routes/web.php
     * agar pengecekan tenant dan peran berjalan sebelum otorisasi.
     */
    private function configurePassport(): void
    {
        Passport::useClientModel(OauthClient::class);
        Passport::authorizationView(fn () => abort(403, 'Klien ini tidak dapat meminta persetujuan manual.'));
        Passport::tokensCan([
            'profile' => 'Membaca profil pengguna yang masuk',
            'sync.read' => 'Membaca semester, pengguna, kelas, dan peserta',
            'grades.write' => 'Mengirim komponen nilai tugas',
        ]);
    }

    /**
     * Batas permintaan API integrasi dihitung per klien OAuth (klaim `aud` pada token yang sudah tervalidasi).
     */
    private function configureIntegrationRateLimit(): void
    {
        RateLimiter::for('integration', function (Request $request): Limit {
            $segments = explode('.', (string) $request->bearerToken());
            $payload = json_decode((string) base64_decode(strtr($segments[1] ?? '', '-_', '+/')), true);
            $clientId = $payload['aud'][0] ?? $request->ip();

            return Limit::perMinute((int) config('integration.lms.rate_limit_per_minute'))->by('integration:'.$clientId);
        });
    }
}
