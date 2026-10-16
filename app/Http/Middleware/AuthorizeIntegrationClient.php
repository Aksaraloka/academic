<?php

namespace App\Http\Middleware;

use App\Services\IntegrationApps;
use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Contracts\ScopeAuthorizable;
use Laravel\Passport\Exceptions\AuthenticationException;
use Laravel\Passport\Exceptions\MissingScopeException;
use Laravel\Passport\Http\Middleware\ValidateToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang API integrasi untuk token klien server (client credentials). Menolak token milik pengguna (401), meminta
 * minimal satu dari scope yang diberikan (403), dan menyimpan aplikasi pemilik klien di request agar semua pembacaan
 * data dibatasi ke tenant aplikasi itu. Pemakaian: `integration.client:sync.read` atau `integration.client:a,b` (salah satu).
 */
class AuthorizeIntegrationClient extends ValidateToken
{
    public function handle(Request $request, Closure $next, string ...$params): Response
    {
        $token = $this->validateToken($request);

        $this->validate($token, ...$params);

        $clientId = $token instanceof AccessToken ? $token->oauth_client_id : null;
        $request->attributes->set(IntegrationApps::REQUEST_ATTRIBUTE, app(IntegrationApps::class)->forClientId($clientId)->key);

        return $next($request);
    }

    protected function validate(ScopeAuthorizable $token, string ...$params): void
    {
        if ($token instanceof AccessToken && $token->oauth_user_id !== null && $token->oauth_user_id !== $token->oauth_client_id) {
            throw new AuthenticationException;
        }

        $clientId = $token instanceof AccessToken ? $token->oauth_client_id : null;
        $app = app(IntegrationApps::class)->forClientId($clientId);

        foreach ($params as $scope) {
            if ($token->can($scope) && $app->allowsScope($scope)) {
                return;
            }
        }

        throw new MissingScopeException($params);
    }
}
