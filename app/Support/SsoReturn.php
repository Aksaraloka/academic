<?php

namespace App\Support;

use App\Models\OauthClient;
use App\Services\IntegrationApp;
use App\Services\IntegrationApps;

/**
 * Membaca URL tujuan setelah login ketika pengguna datang dari alur SSO aplikasi lain (`/oauth/authorize`). Alur ini
 * berakhir dengan redirect ke domain aplikasi asal, yang tidak bisa diikuti navigasi Livewire (fetch), sehingga harus
 * memakai redirect penuh.
 */
final class SsoReturn
{
    /**
     * Tujuan sesudah login adalah rute otorisasi OAuth atau alamat di luar aplikasi ini.
     */
    public static function needsFullRedirect(?string $url): bool
    {
        if (! is_string($url) || $url === '') {
            return false;
        }

        return self::isAuthorizeUrl($url) || ! self::isSameHost($url);
    }

    /**
     * URL absolut milik aplikasi ini yang menuju `/oauth/authorize`.
     */
    public static function isAuthorizeUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path)
            && self::isSameHost($url)
            && rtrim($path, '/') === route('passport.authorizations.authorize', absolute: false);
    }

    /**
     * Aplikasi tujuan SSO menurut `client_id` pada URL otorisasi; null bila bukan URL otorisasi atau klien tidak dikenal.
     */
    public static function appFor(?string $url): ?IntegrationApp
    {
        if (! is_string($url) || ! self::isAuthorizeUrl($url)) {
            return null;
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $clientId = $query['client_id'] ?? null;

        if (! is_string($clientId) || $clientId === '') {
            return null;
        }

        $client = OauthClient::query()->whereKey($clientId)->first();

        return $client === null ? null : app(IntegrationApps::class)->get($client->integrationApp());
    }

    private static function isSameHost(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return $host === null
            || $host === request()->getHost()
            || $host === parse_url((string) config('app.url'), PHP_URL_HOST);
    }
}
