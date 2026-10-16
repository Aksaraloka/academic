<?php

namespace App\Console\Commands;

use App\Models\OauthClient;
use App\Services\IntegrationApps;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

#[Signature('integration:install {app : Aplikasi yang dihubungkan (lms atau admission)} {--rotate : Buat ulang rahasia klien yang sudah ada}')]
#[Description('Membuat klien OAuth (SSO dan server) untuk satu aplikasi Aksaraloka, lalu mencetak kredensialnya')]
class InstallIntegrationApp extends Command
{
    public function handle(ClientRepository $clients, IntegrationApps $apps): int
    {
        return $this->install((string) $this->argument('app'), $clients, $apps);
    }

    /**
     * Buat (atau perbarui) klien SSO dan klien server aplikasi, idempoten: rahasia hanya dicetak saat klien dibuat
     * atau dengan `--rotate`.
     */
    protected function install(string $key, ClientRepository $clients, IntegrationApps $apps): int
    {
        if (! in_array($key, $apps->keys(), true)) {
            $this->components->error('Aplikasi tidak dikenal. Pilih: '.implode(', ', $apps->keys()).'.');

            return self::FAILURE;
        }

        $app = $apps->get($key);

        if ($app->url() === '') {
            $this->components->error(strtoupper($key).'_URL belum diisi di .env.');

            return self::FAILURE;
        }

        $this->ensureKeys();

        $redirectUri = $app->url().'/auth/academic/callback';
        [$ssoName, $serverName] = $this->clientNames($key);

        $sso = $this->findClient($ssoName);
        $ssoSecret = null;

        if ($sso === null) {
            $sso = $clients->createAuthorizationCodeGrantClient($ssoName, [$redirectUri]);
            $ssoSecret = $sso->plainSecret;
        } else {
            $clients->update($sso, $sso->name, [$redirectUri]);

            if ($this->option('rotate')) {
                $clients->regenerateSecret($sso);
                $ssoSecret = $sso->plainSecret;
            }
        }

        $server = $this->findClient($serverName);
        $serverSecret = null;

        if ($server === null) {
            $server = $clients->createClientCredentialsGrantClient($serverName);
            $serverSecret = $server->plainSecret;
        } elseif ($this->option('rotate')) {
            $clients->regenerateSecret($server);
            $serverSecret = $server->plainSecret;
        }

        OauthClient::query()->whereKey([$sso->getKey(), $server->getKey()])->update(['app' => $key]);

        $this->components->info("Klien OAuth siap. Salin nilai berikut ke .env {$app->label()}:");
        $this->line('ACADEMIC_URL='.config('app.url'));
        $this->line('ACADEMIC_SSO_CLIENT_ID='.$sso->getKey());
        $this->line('ACADEMIC_SSO_CLIENT_SECRET='.($ssoSecret ?? '<tidak dapat ditampilkan ulang; jalankan dengan --rotate>'));
        $this->line('ACADEMIC_SERVER_CLIENT_ID='.$server->getKey());
        $this->line('ACADEMIC_SERVER_CLIENT_SECRET='.($serverSecret ?? '<tidak dapat ditampilkan ulang; jalankan dengan --rotate>'));
        $this->line('ACADEMIC_WEBHOOK_SECRET=<sama dengan '.strtoupper($key).'_WEBHOOK_SECRET di .env ini>');

        return self::SUCCESS;
    }

    /**
     * @return array{string, string} nama klien SSO dan klien server
     */
    private function clientNames(string $key): array
    {
        return $key === IntegrationApps::LMS
            ? [config('integration.clients.sso'), config('integration.clients.server')]
            : [config("integration.clients.{$key}.sso"), config("integration.clients.{$key}.server")];
    }

    private function findClient(string $name): ?OauthClient
    {
        return OauthClient::query()->where('name', $name)->where('revoked', false)->first();
    }

    private function ensureKeys(): void
    {
        $hasKeys = config('passport.private_key') !== null
            || file_exists(Passport::keyPath('oauth-private.key'));

        if (! $hasKeys) {
            $this->call('passport:keys');
        }
    }
}
