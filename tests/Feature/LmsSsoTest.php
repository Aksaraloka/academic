<?php

use App\Enums\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->keyDirectory = sys_get_temp_dir().'/academic-passport-keys-'.getmypid();
    File::ensureDirectoryExists($this->keyDirectory);
    Passport::loadKeysFrom($this->keyDirectory);
    Artisan::call('passport:keys', ['--force' => true]);

    $this->tenant = Tenant::factory()->create();
    config([
        'integration.lms.url' => 'http://aksaraloka.test',
        'integration.lms.tenant_id' => $this->tenant->id,
    ]);

    $this->ssoClient = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        'Aksaraloka LMS (SSO)',
        ['http://aksaraloka.test/auth/academic/callback'],
    );
});

afterEach(function () {
    File::deleteDirectory($this->keyDirectory);
});

/**
 * Menjalankan alur authorization code + PKCE sampai mendapat token akses pengguna.
 */
function ssoAccessTokenFor(User $user): string
{
    $verifier = str_repeat('a', 64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    $client = test()->ssoClient;

    $response = test()->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'http://aksaraloka.test/auth/academic/callback',
        'response_type' => 'code',
        'scope' => 'profile',
        'state' => 'abc',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]));

    $response->assertRedirectContains('http://aksaraloka.test/auth/academic/callback?');
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    expect($query['state'])->toBe('abc');

    auth()->forgetGuards();

    $token = test()->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'client_secret' => $client->plainSecret,
        'redirect_uri' => 'http://aksaraloka.test/auth/academic/callback',
        'code' => $query['code'],
        'code_verifier' => $verifier,
    ])->assertOk();

    return $token->json('access_token');
}

it('lets a user of the LMS tenant sign in with the authorization code flow and read their profile', function () {
    $user = User::factory()->role(Role::Student)->create(['tenant_id' => $this->tenant->id, 'email' => 'rina@kampus.ac.id', 'name' => 'Rina Putri']);
    Student::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $user->id, 'nim' => '1301201234', 'status' => 'cuti']);

    $accessToken = ssoAccessTokenFor($user);

    $this->withToken($accessToken)->getJson('/api/integration/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.name', 'Rina Putri')
        ->assertJsonPath('data.email', 'rina@kampus.ac.id')
        ->assertJsonPath('data.identity_number', '1301201234')
        ->assertJsonPath('data.role', 'student')
        ->assertJsonPath('data.is_homeroom', false)
        ->assertJsonPath('data.status', 'cuti');
});

it('reports the NIDN of a lecturer and falls back to NIP', function () {
    $user = User::factory()->role(Role::Teacher)->create(['tenant_id' => $this->tenant->id]);
    $teacher = Teacher::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $user->id, 'nip' => '1990', 'nidn' => '0412']);

    expect($user->fresh()->identityNumber())->toBe('0412');

    $teacher->update(['nidn' => null]);

    expect($user->fresh()->identityNumber())->toBe('1990');
});

it('refuses SSO for a user of another tenant', function () {
    $outsider = User::factory()->role(Role::Student)->create();

    $this->actingAs($outsider)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $this->ssoClient->getKey(),
        'redirect_uri' => 'http://aksaraloka.test/auth/academic/callback',
        'response_type' => 'code',
        'scope' => 'profile',
    ]))->assertForbidden();
});

it('refuses SSO for roles without LMS access', function (Role $role) {
    $user = User::factory()->role($role)->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $this->ssoClient->getKey(),
        'redirect_uri' => 'http://aksaraloka.test/auth/academic/callback',
        'response_type' => 'code',
        'scope' => 'profile',
    ]))->assertForbidden();
})->with([Role::Duty, Role::Finance]);

it('refuses SSO when the integration tenant is not configured', function () {
    config(['integration.lms.tenant_id' => null]);
    $user = User::factory()->role(Role::Admin)->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($user)->get('/oauth/authorize?client_id=x&response_type=code')->assertForbidden();
});

it('sends guests to the Academic login page before authorizing', function () {
    $this->get('/oauth/authorize?'.http_build_query([
        'client_id' => $this->ssoClient->getKey(),
        'redirect_uri' => 'http://aksaraloka.test/auth/academic/callback',
        'response_type' => 'code',
        'scope' => 'profile',
    ]))->assertRedirect(route('login'));
});

it('rejects /me without a token and with a token lacking the profile scope', function () {
    $user = User::factory()->role(Role::Student)->create(['tenant_id' => $this->tenant->id]);

    $this->getJson('/api/integration/v1/me')->assertUnauthorized();

    Passport::actingAs($user, ['sync.read']);
    $this->getJson('/api/integration/v1/me')->assertForbidden();
});

it('does not serve /me to a client credentials token', function () {
    $server = app(ClientRepository::class)->createClientCredentialsGrantClient('Aksaraloka LMS (Server)');

    $accessToken = $this->postJson('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $server->getKey(),
        'client_secret' => $server->plainSecret,
        'scope' => 'sync.read',
    ])->assertOk()->json('access_token');

    $this->withToken($accessToken)->getJson('/api/integration/v1/me')->assertUnauthorized();
});

it('rejects a /me token whose user left the LMS tenant or lost LMS access', function () {
    $outsider = User::factory()->role(Role::Student)->create();
    Passport::actingAs($outsider, ['profile']);

    $this->getJson('/api/integration/v1/me')->assertForbidden();

    $finance = User::factory()->role(Role::Finance)->create(['tenant_id' => $this->tenant->id]);
    Passport::actingAs($finance, ['profile']);

    $this->getJson('/api/integration/v1/me')->assertForbidden();
});

it('creates the OAuth clients idempotently with integration:install-lms', function () {
    $this->ssoClient->delete();

    Artisan::call('integration:install-lms');
    $first = Artisan::output();

    expect($first)->toContain('ACADEMIC_SSO_CLIENT_ID=')->not->toContain('tidak dapat ditampilkan ulang');

    Artisan::call('integration:install-lms');
    $second = Artisan::output();

    expect(Passport::client()->newQuery()->where('name', 'Aksaraloka LMS (Server)')->count())->toBe(1)
        ->and(Passport::client()->newQuery()->where('name', 'Aksaraloka LMS (SSO)')->count())->toBe(1)
        ->and($second)->toContain('tidak dapat ditampilkan ulang');
});
