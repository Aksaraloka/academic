<?php

namespace App\Http\Middleware;

use App\Services\LmsIntegration;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLmsAccess
{
    public function __construct(private LmsIntegration $lms) {}

    /**
     * Tolak pengguna yang sudah masuk tetapi tidak berhak memakai LMS (tenant lain atau peran tanpa akses).
     * Tamu dilewatkan agar diarahkan ke halaman masuk lebih dulu.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api') ?? $request->user();

        if ($user !== null) {
            $reason = $this->lms->denialReason($user);

            abort_if($reason !== null, 403, $reason);
        }

        return $next($request);
    }
}
