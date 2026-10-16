<?php

use App\Enums\Role;
use App\Jobs\DeliverIntegrationEvent;
use App\Models\IntegrationEvent;
use App\Models\OauthClient;
use App\Models\User;
use App\Services\IntegrationApps;
use App\Services\IntegrationEvents;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->keyDirectory = sys_get_temp_dir().'/academic-apps-keys-'.getmypid();
    File::ensureDirectoryExists($this->keyDirectory);
    Passport::loadKeysFrom($this->keyDirectory);
    Artisan::call('passport:keys', ['--force' => true]);

    Queue::fake();

    $this->lmsTenant = makeTenant();
    $this->admissionTenant = makeTenant();
    config([
        'integration.lms.url' => 'http://lms.test',
        'integration.lms.tenant_id' => $this->lmsTenant->id,
        'integration.lms.webhook_secret' => 'lms-secret',
        'integration.apps.admission.url' => 'http://pmb.test',
        'integration.apps.admission.tenant_id' => $this->admissionTenant->id,
        'integration.apps.admission.webhook_secret' => 'pmb-secret',
    ]);

    $clients = app(ClientRepository::class);
    $this->lmsSso = $clients->createAuthorizationCodeGrantClient('Aksaraloka LMS (SSO)', ['http://lms.test/auth/academic/callback']);
    $this->lmsServer = $clients->createClientCredentialsGrantClient('Aksaraloka LMS (Server)');
    $this->pmbSso = $clients->createAuthorizationCodeGrantClient('Aksaraloka Admission (SSO)', ['http://pmb.test/auth/academic/callback']);
    $this->pmbServer = $clients->createClientCredentialsGrantClient('Aksaraloka Admission (Server)');
    OauthClient::query()->whereKey([$this->pmbSso->getKey(), $this->pmbServer->getKey()])->update(['app' => 'admission']);
    OauthClient::query()->whereKey([$this->lmsSso->getKey(), $this->lmsServer->getKey()])->update(['app' => 'lms']);
});

afterEach(fn () => File::deleteDirectory($this->keyDirectory));

/**
 * @return array<string, mixed>
 */
function authorizeQuery(object $client, string $redirect, string $scope = 'profile'): array
{
    $verifier = str_repeat('c', 64);

    return [
        'client_id' => $client->getKey(), 'redirect_uri' => $redirect, 'response_type' => 'code', 'scope' => $scope, 'state' => 's',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='), 'code_challenge_method' => 'S256',
    ];
}

function ssoTokenFor(User $user, object $client, string $redirect): string
{
    $response = test()->actingAs($user)->get('/oauth/authorize?'.http_build_query(authorizeQuery($client, $redirect)));
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    auth()->forgetGuards();

    return test()->postJson('/oauth/token', [
        'grant_type' => 'authorization_code', 'client_id' => $client->getKey(), 'client_secret' => $client->plainSecret,
        'redirect_uri' => $redirect, 'code' => $query['code'], 'code_verifier' => str_repeat('c', 64),
    ])->assertOk()->json('access_token');
}

function clientToken(object $client, string $scope): string
{
    return test()->postJson('/oauth/token', [
        'grant_type' => 'client_credentials', 'client_id' => $client->getKey(), 'client_secret' => $client->plainSecret, 'scope' => $scope,
    ])->assertOk()->json('access_token');
}

describe('SSO per application', function () {
    it('lets Admission staff roles sign in and refuses everyone else', function (Role $role, int $status) {
        $user = User::factory()->role($role)->create(['tenant_id' => $this->admissionTenant->id]);

        $this->actingAs($user)->get('/oauth/authorize?'.http_build_query(authorizeQuery($this->pmbSso, 'http://pmb.test/auth/academic/callback')))
            ->assertStatus($status);
    })->with([
        'admin' => [Role::Admin, 302], 'principal' => [Role::Principal, 302], 'curriculum' => [Role::Curriculum, 302], 'finance' => [Role::Finance, 302],
        'student' => [Role::Student, 403], 'teacher' => [Role::Teacher, 403], 'duty' => [Role::Duty, 403], 'guardian' => [Role::Guardian, 403],
    ]);

    it('refuses staff of a tenant that is not connected to Admission', function () {
        $user = User::factory()->role(Role::Admin)->create(['tenant_id' => $this->lmsTenant->id]);

        $this->actingAs($user)->get('/oauth/authorize?'.http_build_query(authorizeQuery($this->pmbSso, 'http://pmb.test/auth/academic/callback')))->assertForbidden();
    });

    it('keeps LMS rules for LMS clients: finance refused, teachers and students allowed', function () {
        foreach ([[Role::Finance, 403], [Role::Teacher, 302], [Role::Student, 302]] as [$role, $status]) {
            $user = User::factory()->role($role)->create(['tenant_id' => $this->lmsTenant->id]);

            $this->actingAs($user)->get('/oauth/authorize?'.http_build_query(authorizeQuery($this->lmsSso, 'http://lms.test/auth/academic/callback')))->assertStatus($status);
        }
    });

    it('does not let an Admission-tenant admin sign in to the LMS client, nor the reverse', function () {
        $pmbAdmin = User::factory()->role(Role::Admin)->create(['tenant_id' => $this->admissionTenant->id]);
        $lmsAdmin = User::factory()->role(Role::Admin)->create(['tenant_id' => $this->lmsTenant->id]);

        $this->actingAs($pmbAdmin)->get('/oauth/authorize?'.http_build_query(authorizeQuery($this->lmsSso, 'http://lms.test/auth/academic/callback')))->assertForbidden();
        $this->actingAs($lmsAdmin)->get('/oauth/authorize?'.http_build_query(authorizeQuery($this->pmbSso, 'http://pmb.test/auth/academic/callback')))->assertForbidden();
    });

    it('treats a client without an app marker as the LMS', function () {
        OauthClient::query()->whereKey($this->lmsSso->getKey())->update(['app' => null]);
        $teacher = User::factory()->role(Role::Teacher)->create(['tenant_id' => $this->lmsTenant->id]);

        $this->actingAs($teacher)->get('/oauth/authorize?'.http_build_query(authorizeQuery($this->lmsSso, 'http://lms.test/auth/academic/callback')))->assertStatus(302);
    });

    it('serves /me with the original Academic role to Admission', function () {
        $curriculum = User::factory()->role(Role::Curriculum)->create(['tenant_id' => $this->admissionTenant->id]);
        $token = ssoTokenFor($curriculum, $this->pmbSso, 'http://pmb.test/auth/academic/callback');

        $this->withToken($token)->getJson('/api/integration/v1/me')->assertOk()->assertJsonPath('data.role', 'curriculum')->assertJsonPath('data.id', $curriculum->id);
    });

    it('serves /me with the mapped role to the LMS', function () {
        $curriculum = User::factory()->role(Role::Curriculum)->create(['tenant_id' => $this->lmsTenant->id]);
        $token = ssoTokenFor($curriculum, $this->lmsSso, 'http://lms.test/auth/academic/callback');

        $this->withToken($token)->getJson('/api/integration/v1/me')->assertOk()->assertJsonPath('data.role', 'principal');
    });

    it('answers 403 on /me when an Admission token belongs to a user who lost access', function () {
        $admin = User::factory()->role(Role::Admin)->create(['tenant_id' => $this->admissionTenant->id]);
        $token = ssoTokenFor($admin, $this->pmbSso, 'http://pmb.test/auth/academic/callback');
        $admin->update(['role' => Role::Student]);

        $this->withToken($token)->getJson('/api/integration/v1/me')->assertForbidden();
    });
});

describe('scopes per application', function () {
    it('gives an SSO client only the profile scope', function () {
        $admin = User::factory()->role(Role::Admin)->create(['tenant_id' => $this->admissionTenant->id]);
        $token = ssoTokenFor($admin, $this->pmbSso, 'http://pmb.test/auth/academic/callback');

        $this->withToken($token)->getJson('/api/integration/v1/terms')->assertUnauthorized();
    });

    it('lets the Admission server client read terms with admission.read but not LMS data', function () {
        $token = clientToken($this->pmbServer, 'admission.read');
        $term = makeTerm($this->admissionTenant);
        $lmsTerm = makeTerm($this->lmsTenant, ['name' => 'TERM LMS']);
        $class = makeClass($this->admissionTenant, ['term' => $term]);

        $response = $this->withToken($token)->getJson('/api/integration/v1/terms')->assertOk();

        expect(collect($response->json('data'))->pluck('id')->all())->toBe([$term->id])->and($response->getContent())->not->toContain('TERM LMS');
        $this->withToken($token)->getJson("/api/integration/v1/terms/{$term->id}/classes")->assertForbidden();
        $this->withToken($token)->getJson("/api/integration/v1/classes/{$class->id}/roster")->assertForbidden();
        $this->withToken($token)->postJson("/api/integration/v1/classes/{$class->id}/grades", ['component' => 'assignment', 'submitted_by' => 1, 'scores' => [['user_id' => 1, 'score' => 5]]])->assertForbidden();
        expect($lmsTerm->id)->not->toBe($term->id);
    });

    it('does not give the Admission client the LMS scopes even when requested', function () {
        $token = clientToken($this->pmbServer, 'sync.read grades.write');
        makeTerm($this->admissionTenant);

        $this->withToken($token)->getJson('/api/integration/v1/terms')->assertForbidden();
    });

    it('does not give the LMS client the Admission scopes even when requested', function () {
        $token = clientToken($this->lmsServer, 'admission.read admission.write');
        makeTerm($this->lmsTenant);

        $this->withToken($token)->getJson('/api/integration/v1/terms')->assertForbidden();
    });

    it('serves the LMS client with sync.read from the LMS tenant only', function () {
        $lmsTerm = makeTerm($this->lmsTenant);
        makeTerm($this->admissionTenant, ['name' => 'TERM PMB']);

        $response = $this->withToken(clientToken($this->lmsServer, 'sync.read'))->getJson('/api/integration/v1/terms')->assertOk();

        expect(collect($response->json('data'))->pluck('id')->all())->toBe([$lmsTerm->id])->and($response->getContent())->not->toContain('TERM PMB');
    });

    it('answers 403 when the Admission tenant is not configured', function () {
        config(['integration.apps.admission.tenant_id' => null]);

        $this->withToken(clientToken($this->pmbServer, 'admission.read'))->getJson('/api/integration/v1/terms')->assertForbidden();
    });

    it('rejects an unknown or revoked-looking token for either application', function () {
        $this->withToken('bukan-token')->getJson('/api/integration/v1/terms')->assertUnauthorized();
    });
});

describe('configuration', function () {
    it('reads the new apps keys and still honours the legacy LMS keys', function () {
        $apps = app(IntegrationApps::class);

        expect($apps->get('lms')->url())->toBe('http://lms.test')
            ->and($apps->get('admission')->url())->toBe('http://pmb.test')
            ->and($apps->get('admission')->tenantId())->toBe($this->admissionTenant->id)
            ->and($apps->get('admission')->webhookUrl())->toBe('http://pmb.test/webhooks/academic');

        config(['integration.lms.url' => null, 'integration.apps.lms.url' => 'http://baru.test/']);

        expect($apps->get('lms')->url())->toBe('http://baru.test');
    });

    it('is configured only with a URL and a tenant, and publishes only with a secret', function () {
        $apps = app(IntegrationApps::class);

        config(['integration.apps.admission.webhook_secret' => '']);
        expect($apps->get('admission')->isConfigured())->toBeTrue()->and($apps->get('admission')->canPublishEvents())->toBeFalse();

        config(['integration.apps.admission.url' => '']);
        expect($apps->get('admission')->isConfigured())->toBeFalse();
    });

    it('lists the SSO roles and scopes of each application', function () {
        $apps = app(IntegrationApps::class);

        expect(array_keys($apps->get('admission')->ssoRoles()))->toBe(['admin', 'principal', 'curriculum', 'finance'])
            ->and($apps->get('admission')->scopes())->toBe(['profile', 'admission.read', 'admission.write'])
            ->and($apps->get('lms')->scopes())->toBe(['profile', 'sync.read', 'grades.write'])
            ->and($apps->get('lms')->ssoRoles())->toHaveKey('teacher')->not->toHaveKey('finance')
            ->and(fn () => $apps->get('lain'))->toThrow(InvalidArgumentException::class);
    });
});

describe('integration:install', function () {
    beforeEach(function () {
        OauthClient::query()->delete();
    });

    it('creates the Admission clients with the app marker and the Admission redirect', function () {
        $this->artisan('integration:install admission')
            ->expectsOutputToContain('ACADEMIC_SSO_CLIENT_ID=')->expectsOutputToContain('ACADEMIC_SERVER_CLIENT_SECRET=')
            ->expectsOutputToContain('ADMISSION_WEBHOOK_SECRET')->assertSuccessful();

        $sso = OauthClient::where('name', 'Aksaraloka Admission (SSO)')->firstOrFail();
        $server = OauthClient::where('name', 'Aksaraloka Admission (Server)')->firstOrFail();

        expect($sso->app)->toBe('admission')->and($server->app)->toBe('admission')
            ->and($sso->redirect_uris)->toBe(['http://pmb.test/auth/academic/callback'])
            ->and(OauthClient::where('name', 'like', 'Aksaraloka LMS%')->count())->toBe(0);
    });

    it('is idempotent and prints secrets only when created or rotated', function () {
        $this->artisan('integration:install admission')->assertSuccessful();
        $this->artisan('integration:install admission')->expectsOutputToContain('tidak dapat ditampilkan ulang')->assertSuccessful();
        $this->artisan('integration:install admission --rotate')->assertSuccessful();

        expect(OauthClient::where('app', 'admission')->count())->toBe(2);
    });

    it('keeps integration:install-lms as an alias for the LMS', function () {
        $this->artisan('integration:install-lms')->assertSuccessful();
        $this->artisan('integration:install lms')->expectsOutputToContain('tidak dapat ditampilkan ulang')->assertSuccessful();

        expect(OauthClient::where('app', 'lms')->count())->toBe(2)
            ->and(OauthClient::where('name', 'Aksaraloka LMS (SSO)')->firstOrFail()->redirect_uris)->toBe(['http://lms.test/auth/academic/callback']);
    });

    it('fails for an unknown app and for an app without a URL', function () {
        $this->artisan('integration:install sekolah')->expectsOutputToContain('Aplikasi tidak dikenal')->assertFailed();

        config(['integration.apps.admission.url' => '']);
        $this->artisan('integration:install admission')->expectsOutputToContain('ADMISSION_URL belum diisi')->assertFailed();
    });
});

describe('outbox per application', function () {
    it('routes events to the apps that care about them and tags the app', function () {
        Queue::fake();
        $events = app(IntegrationEvents::class);

        $events->publish('class.updated', ['class_id' => 1]);
        $events->publish('program.updated', ['program_id' => 1]);
        $events->publish('term.updated', ['term_id' => 1]);

        $rows = IntegrationEvent::query()->orderBy('type')->orderBy('app')->get()->map(fn ($e) => [$e->app, $e->type, $e->tenant_id])->all();

        expect($rows)->toBe([
            ['lms', 'class.updated', $this->lmsTenant->id],
            ['admission', 'program.updated', $this->admissionTenant->id],
            ['admission', 'term.updated', $this->admissionTenant->id],
            ['lms', 'term.updated', $this->lmsTenant->id],
        ]);
        Queue::assertPushed(DeliverIntegrationEvent::class, 4);
    });

    it('skips an app that is not configured and an event of another tenant', function () {
        Queue::fake();
        config(['integration.apps.admission.webhook_secret' => '']);
        $events = app(IntegrationEvents::class);

        $events->publish('term.updated', ['term_id' => 1]);
        expect(IntegrationEvent::pluck('app')->all())->toBe(['lms']);

        IntegrationEvent::query()->delete();
        config(['integration.apps.admission.webhook_secret' => 'pmb-secret']);
        $events->publish('term.updated', ['term_id' => 2], $this->admissionTenant->id);

        expect(IntegrationEvent::pluck('app')->all())->toBe(['admission']);
    });

    it('can target a single app and never merges events of different apps', function () {
        Queue::fake();
        $events = app(IntegrationEvents::class);

        $events->publish('term.updated', ['term_id' => 1], null, 'admission');
        $events->publish('term.updated', ['term_id' => 1], null, 'admission');
        $events->publish('term.updated', ['term_id' => 1], null, 'lms');

        expect(IntegrationEvent::count())->toBe(2);
    });

    it('delivers an Admission event to the Admission URL signed with the Admission secret', function () {
        Http::fake(['pmb.test/*' => Http::response('', 200)]);
        $event = IntegrationEvent::factory()->create(['tenant_id' => $this->admissionTenant->id, 'app' => 'admission', 'type' => 'program.updated', 'payload' => ['program_id' => 4]]);

        app(IntegrationEvents::class)->deliver($event);

        Http::assertSent(function (Request $request) use ($event): bool {
            $timestamp = $request->header('X-Aksaraloka-Timestamp')[0];

            return $request->url() === 'http://pmb.test/webhooks/academic'
                && $request->header('X-Aksaraloka-Event')[0] === 'program.updated'
                && $request->header('X-Aksaraloka-Event-Id')[0] === $event->id
                && $request->header('X-Aksaraloka-Signature')[0] === 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->body(), 'pmb-secret');
        });
        expect($event->refresh()->isDelivered())->toBeTrue();
    });

    it('names the right application in a delivery error', function () {
        Http::fake(['pmb.test/*' => Http::response('', 500)]);
        $event = IntegrationEvent::factory()->create(['tenant_id' => $this->admissionTenant->id, 'app' => 'admission']);

        expect(fn () => app(IntegrationEvents::class)->deliver($event))->toThrow(RuntimeException::class, 'Admission menjawab HTTP 500');
    });

    it('does not send an event of an app that was switched off meanwhile', function () {
        Http::fake();
        $event = IntegrationEvent::factory()->create(['tenant_id' => $this->admissionTenant->id, 'app' => 'admission']);
        config(['integration.apps.admission.webhook_secret' => '']);

        (new DeliverIntegrationEvent($event->id))->handle(app(IntegrationEvents::class));

        Http::assertNothingSent();
    });

    it('lists failures of every app with integration:events and retries them', function () {
        Queue::fake();
        $lms = IntegrationEvent::factory()->failed()->create(['tenant_id' => $this->lmsTenant->id, 'app' => 'lms']);
        $pmb = IntegrationEvent::factory()->failed()->create(['tenant_id' => $this->admissionTenant->id, 'app' => 'admission']);
        $foreign = IntegrationEvent::factory()->failed()->create(['tenant_id' => $this->lmsTenant->id, 'app' => 'admission']);

        $this->artisan('integration:events')->expectsOutputToContain('0 event terkirim, 0 menunggu, 2 gagal.')->assertSuccessful();
        $this->artisan('integration:events --retry-failed')->expectsOutputToContain('2 event dikirim ulang.')->assertSuccessful();

        Queue::assertPushed(DeliverIntegrationEvent::class, 2);
        expect($lms->refresh()->attempts)->toBe(0)->and($pmb->refresh()->attempts)->toBe(0)->and($foreign->refresh()->attempts)->toBe(3);
    });
});
