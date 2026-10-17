<?php

namespace App\Http\Middleware;

use App\Services\IntegrationApps;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tolak pengguna yang sudah masuk tetapi tidak berhak memakai aplikasi tujuan SSO (tenant lain atau peran tanpa akses).
 * Aplikasi ditentukan dari klien OAuth (`client_id` pada otorisasi, atau klien pemilik token pada `/me`); klien tanpa
 * penanda dianggap LMS. Tamu dilewatkan agar diarahkan ke halaman masuk lebih dulu.
 */
class EnsureLmsAccess
{
    public function __construct(private IntegrationApps $apps) {}

    public function handle(Request $request, Closure $next): Response
    {
        $app = $this->apps->fromRequest($request);
        $request->attributes->set(IntegrationApps::REQUEST_ATTRIBUTE, $app->key);

        $user = $request->user('api') ?? $request->user();

        if ($user !== null) {
            $reason = $app->denialReason($user);

            if ($reason !== null) {
                return $this->deny($request, $app->label(), $reason, $user->name, $user->email);
            }
        }

        return $next($request);
    }

    /**
     * Halaman web (otorisasi SSO) mendapat halaman penolakan yang ramah; pemanggil API tetap menerima 403 JSON.
     */
    private function deny(Request $request, string $appLabel, string $reason, string $name, string $email): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            abort(403, $reason);
        }

        return response()->view('sso.denied', [
            'appLabel' => $appLabel,
            'reason' => $reason,
            'name' => $name,
            'email' => $email,
            'returnUrl' => $request->fullUrl(),
        ], 403);
    }
}
