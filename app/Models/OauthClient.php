<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\Client;
use Laravel\Passport\Scope;

/**
 * Klien OAuth milik kampus sendiri (LMS): tidak perlu layar persetujuan, pengguna cukup masuk.
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
}
