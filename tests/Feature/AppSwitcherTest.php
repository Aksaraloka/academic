<?php

use App\Enums\Role;
use App\Models\OauthClient;
use App\Models\User;
use App\Services\IntegrationApps;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->keyDirectory = sys_get_temp_dir().'/academic-switcher-keys-'.getmypid();
    File::ensureDirectoryExists($this->keyDirectory);
    Passport::loadKeysFrom($this->keyDirectory);
    Artisan::call('passport:keys', ['--force' => true]);

    $this->tenant = makeTenant();
    config([
        'app.url' => 'http://academic.test/',
        'integration.lms.url' => 'http://learn.test',
        'integration.lms.tenant_id' => $this->tenant->id,
        'integration.apps.admission.url' => 'http://pmb.test',
        'integration.apps.admission.tenant_id' => $this->tenant->id,
    ]);

    $clients = app(ClientRepository::class);
    $this->ssoClient = $clients->createAuthorizationCodeGrantClient('Aksaraloka LMS (SSO)', ['http://aksaraloka.test/auth/academic/callback']);
    $this->serverClient = $clients->createClientCredentialsGrantClient('Aksaraloka LMS (Server)');
    OauthClient::query()->whereKey([$this->ssoClient->getKey(), $this->serverClient->getKey()])->update(['app' => 'lms']);
});

afterEach(fn () => File::deleteDirectory($this->keyDirectory));

/**
 * @return array<int, string>
 */
function appKeysFor(User $user): array
{
    return array_column(app(IntegrationApps::class)->linksFor($user), 'key');
}

describe('the list of apps for a user', function () {
    it('always starts with Academic at the configured URL without a trailing slash', function () {
        config(['integration.lms.url' => '', 'integration.apps.admission.url' => '']);

        expect(app(IntegrationApps::class)->linksFor(makeStudentUser($this->tenant)))->toBe([['key' => 'academic', 'label' => 'Academic', 'url' => 'http://academic.test']]);
    });

    it('filters by the role map of Learn and the SSO roles of Admission', function (Role $role, array $expected) {
        expect(appKeysFor(makeStaffUser($this->tenant, $role)))->toBe($expected);
    })->with([
        'admin' => [Role::Admin, ['academic', 'lms', 'admission']],
        'principal' => [Role::Principal, ['academic', 'lms', 'admission']],
        'curriculum' => [Role::Curriculum, ['academic', 'lms', 'admission']],
        'finance' => [Role::Finance, ['academic', 'admission']],
        'teacher' => [Role::Teacher, ['academic', 'lms']],
        'student' => [Role::Student, ['academic', 'lms']],
        'guardian' => [Role::Guardian, ['academic', 'lms']],
        'duty' => [Role::Duty, ['academic']],
    ]);

    it('labels and addresses the other apps from their configuration', function () {
        $links = app(IntegrationApps::class)->linksFor(makeStaffUser($this->tenant, Role::Admin));

        expect($links)->toBe([
            ['key' => 'academic', 'label' => 'Academic', 'url' => 'http://academic.test'],
            ['key' => 'lms', 'label' => 'Learn', 'url' => 'http://learn.test'],
            ['key' => 'admission', 'label' => 'Admission', 'url' => 'http://pmb.test'],
        ]);
    });

    it('hides an app that has no URL or no tenant, and users of another campus', function () {
        config(['integration.lms.url' => '']);
        expect(appKeysFor(makeStaffUser($this->tenant, Role::Admin)))->toBe(['academic', 'admission']);

        config(['integration.lms.url' => 'http://learn.test', 'integration.apps.admission.tenant_id' => null]);
        expect(appKeysFor(makeStaffUser($this->tenant, Role::Admin)))->toBe(['academic', 'lms']);

        expect(appKeysFor(makeStaffUser(makeTenant(), Role::Admin)))->toBe(['academic']);
    });
});

describe('GET /apps', function () {
    it('returns the apps of the signed-in student with a user token', function () {
        $this->withToken(userToken(makeStudentUser($this->tenant), 'profile'))->getJson('/api/integration/v1/apps')->assertOk()
            ->assertExactJson(['data' => [
                ['key' => 'academic', 'label' => 'Academic', 'url' => 'http://academic.test'],
                ['key' => 'lms', 'label' => 'Learn', 'url' => 'http://learn.test'],
            ]]);
    });

    it('returns every app to an admin with a user token', function () {
        $this->withToken(userToken(makeStaffUser($this->tenant, Role::Admin), 'profile'))->getJson('/api/integration/v1/apps')->assertOk()
            ->assertJsonPath('data.*.key', ['academic', 'lms', 'admission']);
    });

    it('rejects a server token, a bad token, and no token', function () {
        $this->getJson('/api/integration/v1/apps')->assertUnauthorized();
        $this->withToken('rusak')->getJson('/api/integration/v1/apps')->assertUnauthorized();
        $this->withToken(serverToken('sync.read'))->getJson('/api/integration/v1/apps')->assertUnauthorized();
    });

    it('is refused once the role of the token owner has no access to the calling app', function () {
        $user = makeStaffUser($this->tenant, Role::Teacher);
        $token = userToken($user, 'profile');
        $user->update(['role' => Role::Duty]);
        auth()->forgetGuards();

        $this->withToken($token)->getJson('/api/integration/v1/apps')->assertForbidden();
    });
});

describe('first-party SSO', function () {
    it('keeps skipping the consent screen for first-party clients', function () {
        $user = makeStaffUser($this->tenant, Role::Admin);

        expect(OauthClient::findOrFail($this->ssoClient->getKey())->skipsAuthorization($user, []))->toBeTrue();

        $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
            'client_id' => $this->ssoClient->getKey(), 'redirect_uri' => 'http://aksaraloka.test/auth/academic/callback', 'response_type' => 'code',
            'scope' => 'profile', 'state' => 's', 'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', str_repeat('b', 64), true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]))->assertRedirectContains('http://aksaraloka.test/auth/academic/callback?code=');
    });
});

describe('the switcher in the layout', function () {
    it('shows other apps to an admin, with sign-in links to /auth/academic', function () {
        $this->actingAs(makeStaffUser($this->tenant, Role::Admin))->get(route('dashboard'))->assertOk()
            ->assertSee('Aplikasi Aksaraloka')
            ->assertSee('href="http://learn.test/auth/academic"', false)
            ->assertSee('href="http://pmb.test/auth/academic"', false);
    });

    it('does not show Admission to a student', function () {
        $this->actingAs(makeStudentUser($this->tenant))->get(route('dashboard'))->assertOk()
            ->assertSee('href="http://learn.test/auth/academic"', false)
            ->assertDontSee('pmb.test')->assertDontSee('Admission');
    });

    it('is absent when no other app is configured', function () {
        config(['integration.lms.url' => '', 'integration.apps.admission.url' => '']);

        $this->actingAs(makeStaffUser($this->tenant, Role::Admin))->get(route('dashboard'))->assertOk()->assertDontSee('Aplikasi Aksaraloka');
    });

    it('is absent for a role with no other app', function () {
        $this->actingAs(makeStaffUser($this->tenant, Role::Duty))->get(route('dashboard'))->assertOk()->assertDontSee('Aplikasi Aksaraloka');
    });

    it('puts the phone version inside the account menu', function () {
        $html = $this->actingAs(makeStaffUser($this->tenant, Role::Admin))->get(route('dashboard'))->assertOk()->getContent();

        expect(substr_count($html, 'class="lg:hidden" role="group"'))->toBe(1)->and(substr_count($html, 'relative hidden lg:block'))->toBe(1);
    });
});
