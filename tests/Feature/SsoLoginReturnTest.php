<?php

use App\Enums\Role;
use App\Livewire\Auth\Login;
use App\Models\OauthClient;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Livewire\Livewire;

beforeEach(function () {
    $this->keyDirectory = sys_get_temp_dir().'/academic-sso-login-keys-'.getmypid();
    File::ensureDirectoryExists($this->keyDirectory);
    Passport::loadKeysFrom($this->keyDirectory);
    Artisan::call('passport:keys', ['--force' => true]);

    $this->tenant = makeTenant();
    config([
        'integration.lms.url' => 'http://lms.test',
        'integration.lms.tenant_id' => $this->tenant->id,
        'integration.apps.admission.url' => 'http://pmb.test',
        'integration.apps.admission.tenant_id' => $this->tenant->id,
    ]);

    $clients = app(ClientRepository::class);
    $this->lmsSso = $clients->createAuthorizationCodeGrantClient('Aksaraloka LMS (SSO)', ['http://lms.test/auth/academic/callback']);
    $this->pmbSso = $clients->createAuthorizationCodeGrantClient('Aksaraloka Admission (SSO)', ['http://pmb.test/auth/academic/callback']);
    OauthClient::query()->whereKey($this->lmsSso->getKey())->update(['app' => 'lms']);
    OauthClient::query()->whereKey($this->pmbSso->getKey())->update(['app' => 'admission']);
});

afterEach(fn () => File::deleteDirectory($this->keyDirectory));

/**
 * @return array{url: string, state: string}
 */
function ssoAuthorizeRequest(string $clientId, string $redirectUri, string $state = 'state-abc'): array
{
    $challenge = rtrim(strtr(base64_encode(hash('sha256', str_repeat('a', 64), true)), '+/', '-_'), '=');

    return [
        'state' => $state,
        'url' => '/oauth/authorize?'.http_build_query([
            'client_id' => $clientId, 'redirect_uri' => $redirectUri, 'response_type' => 'code', 'scope' => 'profile',
            'state' => $state, 'code_challenge' => $challenge, 'code_challenge_method' => 'S256',
        ]),
    ];
}

test('a guest opening the authorize route lands on the login page with the intended url stored', function () {
    $request = ssoAuthorizeRequest($this->lmsSso->getKey(), 'http://lms.test/auth/academic/callback');

    $this->get($request['url'])->assertRedirect(route('login'));

    parse_str(parse_url($request['url'], PHP_URL_QUERY), $expected);
    parse_str(parse_url(session('url.intended'), PHP_URL_QUERY), $stored);

    expect(parse_url(session('url.intended'), PHP_URL_PATH))->toBe('/oauth/authorize')
        ->and($stored)->toEqualCanonicalizing($expected);
});

test('logging in through the form returns to the authorize route with a full redirect and ends at the LMS callback', function () {
    $user = makeStaffUser($this->tenant, Role::Admin);
    $request = ssoAuthorizeRequest($this->lmsSso->getKey(), 'http://lms.test/auth/academic/callback');

    $this->get($request['url'])->assertRedirect(route('login'));
    $intended = session('url.intended');

    $login = Livewire::test(Login::class)
        ->assertSee('Masuk untuk melanjutkan ke Learn.')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect($intended);

    expect($login->effects)->not->toHaveKey('redirectUsingNavigate');

    $response = $this->get($intended);
    $response->assertRedirectContains('http://lms.test/auth/academic/callback?');
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    expect($query['state'])->toBe($request['state'])->and($query)->toHaveKey('code');
});

test('the same flow works for an Admission client with a staff account and opens the staff tab by default', function () {
    $user = makeStaffUser($this->tenant, Role::Admin);
    $request = ssoAuthorizeRequest($this->pmbSso->getKey(), 'http://pmb.test/auth/academic/callback', 'state-pmb');

    $this->get($request['url'])->assertRedirect(route('login'));
    $intended = session('url.intended');

    $login = Livewire::test(Login::class)
        ->assertSee('Masuk untuk melanjutkan ke Admission.')
        ->assertSet('portal', 'lecturer')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect($intended);

    expect($login->effects)->not->toHaveKey('redirectUsingNavigate');

    $response = $this->get($intended);
    $response->assertRedirectContains('http://pmb.test/auth/academic/callback?');
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    expect($query['state'])->toBe('state-pmb');
});

test('a tab chosen explicitly is kept for an Admission login', function () {
    $request = ssoAuthorizeRequest($this->pmbSso->getKey(), 'http://pmb.test/auth/academic/callback');
    $this->get($request['url']);

    Livewire::withQueryParams(['as' => 'student'])->test(Login::class)->assertSet('portal', 'student');
});

test('a plain login still goes to the dashboard with navigation and shows no notice', function () {
    $user = makeStaffUser($this->tenant, Role::Admin);

    $login = Livewire::test(Login::class)
        ->assertDontSee('Masuk untuk melanjutkan')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('dashboard'));

    expect($login->effects)->toHaveKey('redirectUsingNavigate');
});

test('a login that was intended for another host also uses a full redirect', function () {
    $user = makeStaffUser($this->tenant, Role::Admin);
    session(['url.intended' => 'http://elsewhere.test/somewhere']);

    $login = Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect('http://elsewhere.test/somewhere');

    expect($login->effects)->not->toHaveKey('redirectUsingNavigate');
});

test('an unknown client id shows no notice', function () {
    $request = ssoAuthorizeRequest('00000000-0000-0000-0000-000000000000', 'http://lms.test/auth/academic/callback');
    session(['url.intended' => url($request['url'])]);

    Livewire::test(Login::class)->assertDontSee('Masuk untuk melanjutkan');
});

test('a student asking for Admission gets a friendly denial page and can switch accounts while keeping the target', function () {
    $student = makeStudentUser($this->tenant);
    $request = ssoAuthorizeRequest($this->pmbSso->getKey(), 'http://pmb.test/auth/academic/callback');

    $this->get($request['url'])->assertRedirect(route('login'));
    $intended = session('url.intended');

    Livewire::test(Login::class)
        ->set('portal', 'student')
        ->set('email', $student->email)
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect($intended);

    $this->get($intended)
        ->assertForbidden()
        ->assertSee('Akses ke Admission ditolak')
        ->assertSee('Peran akun ini tidak punya akses ke Admission.')
        ->assertSee('Kembali ke Academic')
        ->assertSee('Masuk dengan akun lain');

    $this->post(route('sso.switch-account'), ['return' => $intended])->assertRedirect(route('login'));

    $this->assertGuest();
    expect(session('url.intended'))->toBe($intended);

    Livewire::test(Login::class)->assertSee('Masuk untuk melanjutkan ke Admission.');
});

test('switching accounts ignores a return url that is not the authorize route', function () {
    $user = makeStaffUser($this->tenant, Role::Admin);

    $this->actingAs($user)->post(route('sso.switch-account'), ['return' => 'http://evil.test/oauth/authorize?x=1'])
        ->assertRedirect(route('login'));

    $this->assertGuest();
    expect(session('url.intended'))->toBeNull();
});

test('the token endpoint does not run through the web middleware group', function () {
    $middleware = Route::getRoutes()->getByName('passport.token')->gatherMiddleware();

    expect($middleware)->not->toContain('web')
        ->and($middleware)->not->toContain(PreventRequestForgery::class);
});
