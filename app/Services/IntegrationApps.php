<?php

namespace App\Services;

use App\Models\OauthClient;
use App\Models\User;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Daftar aplikasi yang terhubung ke Academic dan penentuan aplikasi dari klien OAuth atau request yang sedang berjalan.
 */
class IntegrationApps
{
    public const LMS = 'lms';

    public const ADMISSION = 'admission';

    /** Kunci Academic di daftar pengalih aplikasi; Academic selalu ada dan bukan target integrasi. */
    public const ACADEMIC = 'academic';

    /** Atribut request tempat aplikasi pemanggil disimpan oleh middleware integrasi. */
    public const REQUEST_ATTRIBUTE = 'integration.app';

    /** Jenis event webhook per aplikasi (event tak dikenal diarahkan ke LMS). */
    private const EVENTS = [
        self::LMS => ['roster.updated', 'class.updated', 'class.deleted', 'term.updated', 'user.updated', 'student.status_changed', 'grades.locked'],
        self::ADMISSION => ['program.updated', 'fee_rates.updated', 'term.updated'],
    ];

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return [self::LMS, self::ADMISSION];
    }

    public function get(string $key): IntegrationApp
    {
        if (! in_array($key, $this->keys(), true)) {
            throw new InvalidArgumentException("Aplikasi integrasi tidak dikenal: {$key}");
        }

        return $key === self::LMS ? app(LmsIntegration::class) : new IntegrationApp($key);
    }

    /**
     * @return array<string, IntegrationApp>
     */
    public function all(): array
    {
        return collect($this->keys())->mapWithKeys(fn (string $key): array => [$key => $this->get($key)])->all();
    }

    /**
     * Aplikasi Aksaraloka yang boleh dibuka pengguna ini, untuk pengalih aplikasi. Academic selalu pertama (alamat dari
     * `app.url`); aplikasi lain hanya bila terkonfigurasi (alamat dan kampus) dan tenant serta peran pengguna diizinkan
     * `sso_roles`-nya. Alamat tanpa garis miring akhir; tautan masuk berupa `{url}/auth/academic`.
     *
     * @return array<int, array{key: string, label: string, url: string}>
     */
    public function linksFor(User $user): array
    {
        $links = [['key' => self::ACADEMIC, 'label' => 'Academic', 'url' => rtrim((string) config('app.url'), '/')]];

        foreach ($this->all() as $key => $app) {
            if ($app->isConfigured() && $app->canAccess($user)) {
                $links[] = ['key' => $key, 'label' => $app->label(), 'url' => $app->url()];
            }
        }

        return $links;
    }

    /**
     * Aplikasi yang menerima event dengan tipe ini.
     *
     * @return array<int, string>
     */
    public function receiving(string $eventType): array
    {
        $apps = array_keys(array_filter(self::EVENTS, fn (array $types): bool => in_array($eventType, $types, true)));

        return $apps === [] ? [self::LMS] : $apps;
    }

    /**
     * Aplikasi pemilik klien OAuth; klien lama tanpa penanda dianggap LMS.
     */
    public function forClientId(?string $clientId): IntegrationApp
    {
        $key = $clientId === null ? null : OauthClient::query()->whereKey($clientId)->value('app');

        return $this->get(in_array($key, $this->keys(), true) ? $key : self::LMS);
    }

    /**
     * Aplikasi pemanggil request: dari atribut yang diset middleware, dari `client_id` (otorisasi SSO), atau dari token
     * pengguna. Bawaan LMS.
     */
    public function fromRequest(Request $request): IntegrationApp
    {
        $key = $request->attributes->get(self::REQUEST_ATTRIBUTE);

        if (is_string($key)) {
            return $this->get($key);
        }

        $clientId = $request->input('client_id');

        if ($clientId === null && $request->user('api') !== null) {
            $token = $request->user('api')->token();
            $clientId = data_get($token, 'oauth_client_id') ?? data_get($token, 'client_id');
        }

        return $this->forClientId(is_string($clientId) ? $clientId : null);
    }

    /**
     * Aplikasi pemanggil untuk request yang sedang berjalan.
     */
    public function current(): IntegrationApp
    {
        return $this->fromRequest(request());
    }

    public function maxPerPage(): int
    {
        return (int) (config('integration.lms.max_per_page') ?? config('integration.max_per_page', 200));
    }

    public function rateLimitPerMinute(): int
    {
        return (int) (config('integration.lms.rate_limit_per_minute') ?? config('integration.rate_limit_per_minute', 120));
    }
}
