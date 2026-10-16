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

            abort_if($reason !== null, 403, $reason);
        }

        return $next($request);
    }
}
