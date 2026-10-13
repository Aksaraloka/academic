<?php

namespace App\Services;

use App\Models\AttendanceSession;

class QrTokenService
{
    public const WINDOW_SECONDS = 30;

    /**
     * Token QR berganti tiap 30 detik dan ditandatangani server (HMAC dengan APP_KEY).
     */
    public function tokenFor(AttendanceSession $session, ?int $at = null): string
    {
        $window = intdiv($at ?? time(), self::WINDOW_SECONDS);

        return substr(hash_hmac('sha256', "{$session->id}|{$window}", $this->secret()), 0, 32);
    }

    /**
     * Token masih sah di jendela waktu sekarang atau satu jendela sebelumnya (toleransi sinyal lambat).
     */
    public function isValid(AttendanceSession $session, string $token, ?int $at = null): bool
    {
        if ($session->closed_at !== null) {
            return false;
        }

        $now = $at ?? time();

        return hash_equals($this->tokenFor($session, $now), $token)
            || hash_equals($this->tokenFor($session, $now - self::WINDOW_SECONDS), $token);
    }

    public function secondsRemaining(?int $at = null): int
    {
        return self::WINDOW_SECONDS - (($at ?? time()) % self::WINDOW_SECONDS);
    }

    private function secret(): string
    {
        return (string) config('app.key');
    }
}
