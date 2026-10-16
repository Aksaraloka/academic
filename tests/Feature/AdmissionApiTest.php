<?php

use App\Enums\Role;
use App\Jobs\DeliverIntegrationEvent;
use App\Livewire\Programs\Index as ProgramsIndex;
use App\Models\Faculty;
use App\Models\FeeRate;
use App\Models\IntegrationEvent;
use App\Models\OauthClient;
use App\Models\Program;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Livewire\Livewire;

beforeEach(function () {
    $this->keyDirectory = sys_get_temp_dir().'/academic-admission-keys-'.getmypid();
    File::ensureDirectoryExists($this->keyDirectory);
    Passport::loadKeysFrom($this->keyDirectory);
    Artisan::call('passport:keys', ['--force' => true]);

    // Data dibuat lebih dulu; webhook diaktifkan setelahnya (lihat enableAdmissionWebhooks).
    $this->tenant = makeTenant();
    $this->program = makeProgram($this->tenant, 'Informatika');
    $this->other = makeProgram($this->tenant, 'Sistem Informasi');

    $this->lmsTenant = makeTenant();
    config([
        'integration.apps.admission.url' => 'http://pmb.test',
        'integration.apps.admission.tenant_id' => $this->tenant->id,
        'integration.lms.url' => 'http://lms.test',
        'integration.lms.tenant_id' => $this->lmsTenant->id,
    ]);

    $clients = app(ClientRepository::class);
    $this->pmbServer = $clients->createClientCredentialsGrantClient('Aksaraloka Admission (Server)');
    $this->lmsServer = $clients->createClientCredentialsGrantClient('Aksaraloka LMS (Server)');
    OauthClient::query()->whereKey($this->pmbServer->getKey())->update(['app' => 'admission']);
    OauthClient::query()->whereKey($this->lmsServer->getKey())->update(['app' => 'lms']);

    $this->token = clientToken($this->pmbServer, 'admission.read');
});

afterEach(fn () => File::deleteDirectory($this->keyDirectory));

function enableAdmissionWebhooks(object $test, bool $lms = false): void
{
    Queue::fake();
    config(['integration.apps.admission.webhook_secret' => 'pmb-secret']);

    if ($lms) {
        config(['integration.lms.webhook_secret' => 'lms-secret']);
    }
}

/**
 * @return array<int, array{string, string, array<string, mixed>}>
 */
function outbox(): array
{
    return IntegrationEvent::query()->orderBy('app')->orderBy('type')->get()->map(fn (IntegrationEvent $e): array => [$e->app, $e->type, $e->payload])->all();
}

describe('authentication and scopes', function () {
    it('answers 401 without a valid token', function () {
        foreach (['/api/integration/v1/programs', "/api/integration/v1/programs/{$this->program->id}/fee-rates"] as $url) {
            $this->getJson($url)->assertUnauthorized();
            $this->withToken('rusak')->getJson($url)->assertUnauthorized();
        }
    });

    it('answers 403 to tokens without admission.read, including every LMS token', function () {
        foreach ([clientToken($this->pmbServer, 'admission.write'), clientToken($this->lmsServer, 'sync.read'), clientToken($this->lmsServer, 'sync.read grades.write')] as $token) {
            $this->withToken($token)->getJson('/api/integration/v1/programs')->assertForbidden();
            $this->withToken($token)->getJson("/api/integration/v1/programs/{$this->program->id}/fee-rates")->assertForbidden();
        }
    });

    it('answers 403 when the Admission tenant is not configured', function () {
        config(['integration.apps.admission.tenant_id' => null]);

        $this->withToken($this->token)->getJson('/api/integration/v1/programs')->assertForbidden();
    });
});

describe('programs', function () {
    it('lists the programs of the Admission tenant with the fields of the contract', function () {
        $data = $this->withToken($this->token)->getJson('/api/integration/v1/programs')->assertOk()->json('data');

        expect($data)->toHaveCount(2)
            ->and($data[0])->toMatchArray(['id' => $this->program->id, 'code' => $this->program->code, 'name' => 'Informatika', 'degree' => 'S1'])
            ->and($data[0]['faculty'])->toBe(['id' => $this->program->faculty_id, 'name' => 'Fakultas Teknik'])
            ->and($data[0])->toHaveKey('updated_at')
            ->and($data[1]['name'])->toBe('Sistem Informasi');
    });

    it('never shows programs of other tenants', function () {
        $foreign = makeProgram($this->lmsTenant, 'PROGRAM RAHASIA');
        FeeRate::create(['tenant_id' => $this->lmsTenant->id, 'program_id' => $foreign->id, 'kind' => 'ukt', 'amount' => 1]);

        $response = $this->withToken($this->token)->getJson('/api/integration/v1/programs')->assertOk();

        expect($response->getContent())->not->toContain('PROGRAM RAHASIA');
        $this->withToken($this->token)->getJson("/api/integration/v1/programs/{$foreign->id}/fee-rates")->assertNotFound();
        $this->withToken($this->token)->getJson('/api/integration/v1/programs/999999/fee-rates')->assertNotFound();
        $this->withToken($this->token)->getJson('/api/integration/v1/programs/abc/fee-rates')->assertNotFound();
    });

    it('paginates with the standard structure and caps per_page', function () {
        foreach (range(1, 3) as $n) {
            makeProgram($this->tenant, "Prodi {$n}");
        }

        config(['integration.lms.max_per_page' => 2]);
        $response = $this->withToken($this->token)->getJson('/api/integration/v1/programs?per_page=100&page=2')->assertOk();

        expect($response->json('data'))->toHaveCount(2)->and($response->json('meta.total'))->toBe(5)->and($response->json('meta.per_page'))->toBe(2)->and($response->json('links.next'))->not->toBeNull();
    });
});

describe('fee rates', function () {
    it('returns the UKT per group and the BPP of the program, from fee_rates', function () {
        FeeRate::create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program->id, 'kind' => 'ukt', 'ukt_group' => null, 'amount' => 5000000]);
        FeeRate::create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program->id, 'kind' => 'ukt', 'ukt_group' => 2, 'amount' => 3000000]);
        FeeRate::create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program->id, 'kind' => 'ukt', 'ukt_group' => 1, 'amount' => 1000000]);
        FeeRate::create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program->id, 'kind' => 'bpp', 'ukt_group' => null, 'amount' => 750000]);
        FeeRate::create(['tenant_id' => $this->tenant->id, 'program_id' => $this->other->id, 'kind' => 'bpp', 'ukt_group' => null, 'amount' => 999]);

        $data = $this->withToken($this->token)->getJson("/api/integration/v1/programs/{$this->program->id}/fee-rates")->assertOk()->json('data');

        expect($data)->toBe([
            ['kind' => 'bpp', 'ukt_group' => null, 'amount' => 750000],
            ['kind' => 'ukt', 'ukt_group' => null, 'amount' => 5000000],
            ['kind' => 'ukt', 'ukt_group' => 1, 'amount' => 1000000],
            ['kind' => 'ukt', 'ukt_group' => 2, 'amount' => 3000000],
        ]);
    });

    it('returns an empty list for a program without rates', function () {
        $this->withToken($this->token)->getJson("/api/integration/v1/programs/{$this->other->id}/fee-rates")->assertOk()->assertJsonPath('data', []);
    });
});

describe('webhooks to Admission', function () {
    it('fee_rates.updated when a rate is created, changed, or removed, only to Admission', function () {
        enableAdmissionWebhooks($this, lms: true);

        $rate = FeeRate::create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program->id, 'kind' => 'ukt', 'amount' => 100]);
        expect(outbox())->toBe([['admission', 'fee_rates.updated', ['program_id' => $this->program->id]]]);

        IntegrationEvent::query()->delete();
        $rate->update(['amount' => 200]);
        expect(outbox())->toBe([['admission', 'fee_rates.updated', ['program_id' => $this->program->id]]]);

        IntegrationEvent::query()->delete();
        $rate->delete();
        expect(outbox())->toBe([['admission', 'fee_rates.updated', ['program_id' => $this->program->id]]]);
        Queue::assertPushed(DeliverIntegrationEvent::class);
    });

    it('fee_rates.updated for both programs when a rate moves to another program', function () {
        $rate = FeeRate::create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program->id, 'kind' => 'bpp', 'amount' => 100]);
        enableAdmissionWebhooks($this);

        $rate->update(['program_id' => $this->other->id]);

        expect(collect(outbox())->map(fn ($e) => $e[2]['program_id'])->sort()->values()->all())->toBe(collect([$this->program->id, $this->other->id])->sort()->values()->all());
    });

    it('fee_rates.updated through updateOrCreate, as the finance page does', function () {
        enableAdmissionWebhooks($this);

        FeeRate::query()->updateOrCreate(['program_id' => $this->program->id, 'kind' => 'ukt', 'ukt_group' => 3], ['tenant_id' => $this->tenant->id, 'amount' => 4000000]);
        FeeRate::query()->updateOrCreate(['program_id' => $this->program->id, 'kind' => 'ukt', 'ukt_group' => 3], ['tenant_id' => $this->tenant->id, 'amount' => 4500000]);

        expect(outbox())->toBe([['admission', 'fee_rates.updated', ['program_id' => $this->program->id]]]);
    });

    it('program.updated when a program is created or its name, code, degree, or faculty changes', function () {
        enableAdmissionWebhooks($this);

        $new = makeProgram($this->tenant, 'Hukum');
        expect(outbox())->toContain(['admission', 'program.updated', ['program_id' => $new->id]]);

        IntegrationEvent::query()->delete();
        $new->update(['name' => 'Ilmu Hukum']);
        $new->update(['degree' => 'S2']);

        expect(outbox())->toBe([['admission', 'program.updated', ['program_id' => $new->id]]]);
    });

    it('program.updated for every program of a renamed faculty', function () {
        $faculty = Faculty::where('tenant_id', $this->tenant->id)->firstOrFail();
        enableAdmissionWebhooks($this);

        $faculty->update(['name' => 'Fakultas Ilmu Komputer']);

        expect(collect(outbox())->map(fn ($e) => $e[2]['program_id'])->sort()->values()->all())->toBe(collect([$this->program->id, $this->other->id])->sort()->values()->all());
        expect(collect(outbox())->pluck(1)->unique()->all())->toBe(['program.updated']);
    });

    it('term.updated reaches Admission and the LMS when both are enabled', function () {
        $term = makeTerm($this->tenant);
        enableAdmissionWebhooks($this);
        config(['integration.lms.tenant_id' => $this->tenant->id, 'integration.lms.webhook_secret' => 'lms-secret']);

        $term->update(['is_locked' => true]);

        expect(outbox())->toBe([
            ['admission', 'term.updated', ['term_id' => $term->id]],
            ['lms', 'term.updated', ['term_id' => $term->id]],
        ]);
    });

    it('does not send LMS-only events to Admission', function () {
        $class = makeClass($this->tenant);
        enableAdmissionWebhooks($this);

        $class->update(['section' => 'Z']);
        $class->teacher->user->update(['name' => 'Nama Baru']);

        expect(outbox())->toBe([]);
    });

    it('publishes nothing when the Admission secret or tenant is missing, or for another tenant', function () {
        Queue::fake();
        config(['integration.apps.admission.webhook_secret' => '']);
        FeeRate::create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program->id, 'kind' => 'ukt', 'amount' => 1]);
        expect(IntegrationEvent::count())->toBe(0);

        config(['integration.apps.admission.webhook_secret' => 'pmb-secret']);
        $foreign = makeProgram($this->lmsTenant, 'Asing');
        FeeRate::create(['tenant_id' => $this->lmsTenant->id, 'program_id' => $foreign->id, 'kind' => 'ukt', 'amount' => 1]);

        expect(IntegrationEvent::count())->toBe(0);
    });

    it('queues the event for delivery to the Admission URL', function () {
        enableAdmissionWebhooks($this);

        Program::query()->whereKey($this->program->id)->first()->update(['name' => 'Teknik Informatika']);

        $event = IntegrationEvent::firstOrFail();
        expect($event->app)->toBe('admission')->and($event->tenant_id)->toBe($this->tenant->id);
        Queue::assertPushed(DeliverIntegrationEvent::class, fn ($job) => $job->eventId === $event->id);
    });
});

describe('accreditation and fee model', function () {
    it('lists the accreditation (null when empty) and the campus fee model on every program', function () {
        $this->program->update(['accreditation' => 'Unggul']);

        $data = $this->withToken($this->token)->getJson('/api/integration/v1/programs')->assertOk()->json('data');

        expect($data[0])->toMatchArray(['name' => 'Informatika', 'accreditation' => 'Unggul', 'fee_model' => 'ukt'])
            ->and($data[1])->toMatchArray(['name' => 'Sistem Informasi', 'accreditation' => null, 'fee_model' => 'ukt']);

        $this->tenant->update(['settings' => ['fee_model' => 'flat']]);

        expect(collect($this->withToken($this->token)->getJson('/api/integration/v1/programs')->assertOk()->json('data'))->pluck('fee_model')->unique()->all())->toBe(['flat']);
    });

    it('leaves the fee-rates endpoint unchanged', function () {
        FeeRate::create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program->id, 'kind' => 'bpp', 'amount' => 700000]);
        $this->tenant->update(['settings' => ['fee_model' => 'flat']]);

        $this->withToken($this->token)->getJson("/api/integration/v1/programs/{$this->program->id}/fee-rates")->assertOk()
            ->assertJsonPath('data', [['kind' => 'bpp', 'ukt_group' => null, 'amount' => 700000]]);
    });

    it('publishes program.updated when the accreditation changes, only to Admission', function () {
        enableAdmissionWebhooks($this, lms: true);

        $this->program->update(['accreditation' => 'B']);
        expect(outbox())->toBe([['admission', 'program.updated', ['program_id' => $this->program->id]]]);

        IntegrationEvent::query()->delete();
        $this->program->update(['accreditation' => 'B']);
        expect(outbox())->toBe([]);
    });

    it('saves the accreditation on the programs page and publishes the event', function () {
        $admin = makeStaffUser($this->tenant, Role::Admin);
        enableAdmissionWebhooks($this);

        Livewire::actingAs($admin)->test(ProgramsIndex::class)->assertSet("accreditations.{$this->program->id}", '')
            ->set("accreditations.{$this->program->id}", ' Unggul ')->call('saveAccreditation', $this->program->id)->assertHasNoErrors();

        expect($this->program->refresh()->accreditation)->toBe('Unggul')
            ->and(outbox())->toBe([['admission', 'program.updated', ['program_id' => $this->program->id]]]);

        Livewire::actingAs($admin)->test(ProgramsIndex::class)->set("accreditations.{$this->program->id}", '')->call('saveAccreditation', $this->program->id);
        expect($this->program->refresh()->accreditation)->toBeNull();

        Livewire::actingAs($admin)->test(ProgramsIndex::class)->set("accreditations.{$this->program->id}", str_repeat('x', 31))->call('saveAccreditation', $this->program->id)->assertHasErrors();
    });

    it('only admin may edit the accreditation', function () {
        Livewire::actingAs(makeStaffUser($this->tenant, Role::Curriculum))->test(ProgramsIndex::class)
            ->call('saveAccreditation', $this->program->id)->assertForbidden();
    });

    it('publishes fee_rates.updated for every program when the fee model changes, and only then', function () {
        enableAdmissionWebhooks($this, lms: true);

        $this->tenant->update(['settings' => ['fee_model' => 'flat']]);

        expect(collect(outbox())->pluck(1)->unique()->all())->toBe(['fee_rates.updated'])
            ->and(collect(outbox())->pluck(0)->unique()->all())->toBe(['admission'])
            ->and(collect(outbox())->map(fn ($e) => $e[2]['program_id'])->sort()->values()->all())->toBe(collect([$this->program->id, $this->other->id])->sort()->values()->all());

        IntegrationEvent::query()->delete();
        $this->tenant->update(['settings' => ['fee_model' => 'flat', 'va_prefix' => '9999']]);
        $this->tenant->update(['name' => 'Kampus Baru']);
        expect(outbox())->toBe([]);

        $this->tenant->update(['settings' => ['va_prefix' => '9999']]);
        expect(collect(outbox())->pluck(1)->unique()->all())->toBe(['fee_rates.updated']);
    });

    it('does not publish when the stored model is missing and the new one is the UKT default', function () {
        enableAdmissionWebhooks($this);

        $this->tenant->update(['settings' => ['fee_model' => 'ukt']]);

        expect(outbox())->toBe([]);
    });
});
