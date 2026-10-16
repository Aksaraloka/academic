<?php

namespace App\Models;

use App\Services\IntegrationApps;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\Client;
use Laravel\Passport\Scope;

/**
 * Klien OAuth milik kampus sendiri (LMS, Admission): tidak perlu layar persetujuan, pengguna cukup masuk.
 * Kolom `app` menandai aplikasi pemilik; klien lama tanpa penanda dianggap LMS.
 *
 * @property string|null $app
 */
class OauthClient extends Client
{
    /**
     * @param  array<int, Scope>  $scopes
     */
    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        return $this->firstParty();
    }

    public function integrationApp(): string
    {
        return in_array($this->app, [IntegrationApps::LMS, IntegrationApps::ADMISSION], true) ? $this->app : IntegrationApps::LMS;
    }

    /**
     * Scope yang boleh didapat klien ini: klien SSO (authorization code) hanya `profile`; klien server hanya scope yang
     * terdaftar untuk aplikasinya. Dengan begitu klien LMS tidak bisa meminta scope Admission, dan sebaliknya.
     */
    public function hasScope(string $scope): bool
    {
        if (! $this->hasGrantType('client_credentials')) {
            return $scope === 'profile';
        }

        return app(IntegrationApps::class)->get($this->integrationApp())->allowsScope($scope) && $scope !== 'profile';
    }
}
