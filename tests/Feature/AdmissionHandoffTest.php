<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Jobs\FetchAdmissionPhoto;
use App\Jobs\SendPasswordSetupLink;
use App\Livewire\Groups\Index as GroupsIndex;
use App\Livewire\Groups\Show as GroupsShow;
use App\Livewire\Settings\Campus;
use App\Models\AuditLog;
use App\Models\FeeRate;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\IntegrationEvent;
use App\Models\Invoice;
use App\Models\OauthClient;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\StudyGroupMember;
use App\Models\User;
use App\Notifications\AcademicNotice;
use App\Services\AuditLogger;
use App\Services\ClassRoster;
use App\Services\FinanceService;
use App\Services\NimGenerator;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Livewire\Livewire;

beforeEach(function () {
    $this->keyDirectory = sys_get_temp_dir().'/academic-handoff-keys-'.getmypid();
    File::ensureDirectoryExists($this->keyDirectory);
    Passport::loadKeysFrom($this->keyDirectory);
    Artisan::call('passport:keys', ['--force' => true]);

    $this->tenant = makeTenant();
    $this->term = makeTerm($this->tenant);
    $this->program = makeProgram($this->tenant, 'Informatika');
    FeeRate::create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program->id, 'kind' => 'ukt', 'ukt_group' => null, 'amount' => 4000000]);
    FeeRate::create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program->id, 'kind' => 'ukt', 'ukt_group' => 3, 'amount' => 4500000]);
    FeeRate::create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program->id, 'kind' => 'bpp', 'ukt_group' => null, 'amount' => 500000]);

    config([
        'integration.apps.admission.url' => 'http://pmb.test',
        'integration.apps.admission.tenant_id' => $this->tenant->id,
        'integration.apps.admission.webhook_secret' => '',
    ]);

    $clients = app(ClientRepository::class);
    $this->pmbServer = $clients->createClientCredentialsGrantClient('Aksaraloka Admission (Server)');
    $this->lmsServer = $clients->createClientCredentialsGrantClient('Aksaraloka LMS (Server)');
    OauthClient::query()->whereKey($this->pmbServer->getKey())->update(['app' => 'admission']);
    OauthClient::query()->whereKey($this->lmsServer->getKey())->update(['app' => 'lms']);

    $this->token = clientToken($this->pmbServer, 'admission.write');
    $this->url = '/api/integration/v1/admissions/students';
});

afterEach(fn () => File::deleteDirectory($this->keyDirectory));

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function handoffPayload(object $test, array $overrides = []): array
{
    return array_replace_recursive([
        'admission_ref' => 'PMB-2027-000123',
        'program_id' => $test->program->id,
        'term_id' => $test->term->id,
        'batch_year' => 2027,
        'admission_path' => 'Mandiri',
        'ukt_group' => 3,
        'student' => [
            'name' => 'Rina Putri', 'email' => 'Rina.Putri@Contoh.test', 'phone' => '081234567890', 'nik' => '3201010101090001',
            'birth_place' => 'Bandung', 'birth_date' => '2009-05-01', 'gender' => 'P', 'religion' => 'Islam', 'nationality' => 'WNI',
            'address' => 'Jl. Mawar 1', 'city' => 'Bandung', 'province' => 'Jawa Barat', 'postal_code' => '40111', 'previous_school' => 'SMAN 1 Bandung',
        ],
        'initial_payment' => ['amount' => 5000000, 'paid_on' => '2027-07-20', 'method' => 'va', 'reference' => 'ADM-PAY-000987'],
    ], $overrides);
}

function handoff(object $test, array $overrides = [], ?string $token = null)
{
    return test()->withToken($token ?? $test->token)->postJson($test->url, handoffPayload($test, $overrides));
}

describe('successful handoff', function () {
    it('creates the account, student, NIM, first-semester invoice, and confirmed payment in one go', function () {
        Queue::fake();

        $response = handoff($this)->assertCreated();

        $student = Student::where('admission_ref', 'PMB-2027-000123')->firstOrFail();
        $user = User::findOrFail($student->user_id);
        $invoice = Invoice::where('student_id', $student->id)->firstOrFail();
        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $group = StudyGroup::firstOrFail();

        expect($response->json())->toBe([
            'user_id' => $user->id, 'student_id' => $student->id, 'nim' => '27INF0001',
            'study_group' => ['id' => $group->id, 'name' => 'INF 2027 A'], 'photo_status' => 'none',
            'invoice' => ['number' => $invoice->number, 'status' => 'paid', 'total' => 5000000, 'paid' => 5000000],
        ])
            ->and($user->role)->toBe(Role::Student)->and($user->tenant_id)->toBe($this->tenant->id)->and($user->email)->toBe('rina.putri@contoh.test')
            ->and($student->status)->toBe(StudentStatus::Active)->and($student->entry_date->toDateString())->toBe(today()->toDateString())
            ->and($student->program_id)->toBe($this->program->id)->and($student->batch_year)->toBe(2027)->and($student->admission_path)->toBe('Mandiri')
            ->and($student->ukt_group)->toBe(3)->and($student->nik)->toBe('3201010101090001')->and($student->city)->toBe('Bandung')
            ->and($invoice->academic_term_id)->toBe($this->term->id)->and($invoice->created_by)->toBeNull()->and($invoice->items()->pluck('amount', 'description')->all())->toBe(['UKT' => 4500000, 'BPP' => 500000])
            ->and($payment->status)->toBe(PaymentStatus::Confirmed)->and($payment->method)->toBe(PaymentMethod::VirtualAccount)
            ->and($payment->reference)->toBe('ADM-PAY-000987')->and($payment->amount)->toBe(5000000)->and($payment->paid_on->toDateString())->toBe('2027-07-20');
    });

    it('uses the fixed virtual account of the new student', function () {
        Queue::fake();

        handoff($this)->assertCreated();

        $student = Student::firstWhere('admission_ref', 'PMB-2027-000123');

        expect(Invoice::firstOrFail()->va_number)->toBe(substr('8808'.preg_replace('/\D+/', '', $student->nim), 0, 16));
    });

    it('creates an account nobody can sign in to, with a queued password setup link', function () {
        Queue::fake();

        handoff($this)->assertCreated();

        $user = User::firstWhere('email', 'rina.putri@contoh.test');

        expect(Hash::check('', $user->password))->toBeFalse()->and(strlen($user->password))->toBeGreaterThan(40);
        Queue::assertPushed(SendPasswordSetupLink::class, fn (SendPasswordSetupLink $job) => $job->userId === $user->id);
    });

    it('sends the password setup link by email through the default broker', function () {
        Notification::fake();

        handoff($this)->assertCreated();

        $user = User::firstWhere('email', 'rina.putri@contoh.test');
        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    });

    it('does not send the "new invoice" notification for the handoff invoice', function () {
        Notification::fake();

        handoff($this)->assertCreated();

        Notification::assertNotSentTo(User::firstWhere('email', 'rina.putri@contoh.test'), AcademicNotice::class);
    });

    it('accepts every admission path including the new ones', function (string $path) {
        Queue::fake();

        handoff($this, ['admission_path' => $path])->assertCreated();

        expect(Student::firstOrFail()->admission_path)->toBe($path);
    })->with(['SNBP', 'SNBT', 'Mandiri', 'Beasiswa', 'Pindahan', 'Prestasi', 'KIP-K', 'Kerja Sama']);

    it('keeps the old admission paths and adds the new ones', function () {
        expect(Student::ADMISSION_PATHS)->toBe(['SNBP', 'SNBT', 'Mandiri', 'Beasiswa', 'Pindahan', 'Prestasi', 'KIP-K', 'Kerja Sama']);
    });

    it('records the handoff in the audit log under the campus', function () {
        Queue::fake();

        handoff($this)->assertCreated();

        $log = AuditLog::where('action', 'admission.handoff')->firstOrFail();

        expect($log->tenant_id)->toBe($this->tenant->id)->and($log->user_id)->toBeNull()->and($log->auditable_type)->toBe((new Student)->getMorphClass())
            ->and($log->new_values)->toMatchArray(['admission_ref' => 'PMB-2027-000123', 'nim' => '27INF0001', 'paid' => 5000000, 'payment_reference' => 'ADM-PAY-000987']);
    });

    it('falls back to the general UKT rate for an unlisted group and accepts missing optional data', function () {
        Queue::fake();

        handoff($this, ['ukt_group' => 1, 'student' => ['phone' => null, 'nik' => null], 'initial_payment' => ['amount' => 4500000]])->assertCreated()
            ->assertJsonPath('invoice.total', 4500000);

        expect(Student::firstOrFail()->nik)->toBeNull();
    });
});

describe('payment against the invoice', function () {
    it('leaves the invoice partial when the first payment is less than the invoice', function () {
        Queue::fake();

        handoff($this, ['initial_payment' => ['amount' => 2000000]])->assertCreated()
            ->assertJsonPath('invoice.status', 'partial')->assertJsonPath('invoice.paid', 2000000)->assertJsonPath('invoice.total', 5000000);

        expect(Invoice::firstOrFail()->status)->toBe(InvoiceStatus::Partial)->and(Invoice::firstOrFail()->outstanding())->toBe(3000000);
    });

    it('rejects a payment above the invoice and creates nothing', function () {
        Queue::fake();

        handoff($this, ['initial_payment' => ['amount' => 5000001]])->assertUnprocessable()->assertJsonPath('code', 'payment_exceeds_invoice');

        expect(User::count())->toBe(0)->and(Student::count())->toBe(0)->and(Invoice::count())->toBe(0)->and(Payment::count())->toBe(0);
        Queue::assertNothingPushed();
    });

    it('rejects a payment reference that is already recorded', function () {
        Queue::fake();
        handoff($this)->assertCreated();

        handoff($this, ['admission_ref' => 'PMB-2027-000124', 'student' => ['email' => 'lain@contoh.test', 'nik' => '3201010101090002']])
            ->assertUnprocessable()->assertJsonPath('code', 'payment_reference_used');

        expect(Student::count())->toBe(1);
    });

    it('rejects the handoff when the program has no fee rate and rolls everything back', function () {
        Queue::fake();
        $bare = makeProgram($this->tenant, 'Hukum');

        handoff($this, ['program_id' => $bare->id])->assertUnprocessable()->assertJsonPath('code', 'fee_rate_missing');

        expect(User::count())->toBe(0)->and(Student::count())->toBe(0)->and(Invoice::count())->toBe(0);
    });

    it('keeps the ledger single: the payment appears in the normal invoice flow', function () {
        Queue::fake();
        handoff($this, ['initial_payment' => ['amount' => 1000000]])->assertCreated();

        $invoice = Invoice::with('payments')->firstOrFail();

        expect($invoice->paidAmount())->toBe(1000000)->and(app(FinanceService::class)->krsClearance(Student::firstOrFail(), $this->term)['outstanding'])->toBe(4000000);
    });
});

describe('idempotency', function () {
    it('answers 200 with the same body for a repeated admission_ref and creates nothing new', function () {
        Queue::fake();

        $first = handoff($this)->assertCreated()->json();
        $second = handoff($this)->assertOk()->json();

        expect($second)->toBe($first)
            ->and(User::count())->toBe(1)->and(Student::count())->toBe(1)->and(Invoice::count())->toBe(1)->and(Payment::count())->toBe(1);
        Queue::assertPushed(SendPasswordSetupLink::class, 1);
    });

    it('returns the existing result even if the repeated call carries different data', function () {
        Queue::fake();
        $first = handoff($this)->assertCreated()->json();

        $second = handoff($this, ['student' => ['email' => 'berbeda@contoh.test', 'nik' => '9999999999999999'], 'initial_payment' => ['reference' => 'ADM-PAY-LAIN']])->assertOk()->json();

        expect($second)->toBe($first)->and(User::count())->toBe(1);
    });

    it('reports the current invoice state when repeated after a later payment', function () {
        Queue::fake();
        handoff($this, ['initial_payment' => ['amount' => 1000000]])->assertCreated();
        $invoice = Invoice::firstOrFail();
        Payment::create(['tenant_id' => $this->tenant->id, 'invoice_id' => $invoice->id, 'student_id' => $invoice->student_id, 'amount' => 4000000, 'method' => PaymentMethod::BankTransfer, 'paid_on' => today(), 'status' => PaymentStatus::Confirmed]);
        app(FinanceService::class)->refresh($invoice);

        handoff($this)->assertOk()->assertJsonPath('invoice.status', 'paid')->assertJsonPath('invoice.paid', 5000000);
    });

    it('treats the same ref of another tenant as a separate handoff', function () {
        Queue::fake();
        handoff($this)->assertCreated();

        $other = makeTenant();
        $program = makeProgram($other, 'Informatika');
        Student::factory()->create(['tenant_id' => $other->id, 'admission_ref' => 'PMB-2027-000123', 'nim' => 'X1', 'program_id' => $program->id]);

        handoff($this, ['admission_ref' => 'PMB-2027-000999', 'student' => ['email' => 'baru@contoh.test', 'nik' => '3201010101090003'], 'initial_payment' => ['reference' => 'ADM-PAY-2']])->assertCreated();

        expect(Student::where('tenant_id', $this->tenant->id)->count())->toBe(2);
    });
});

describe('conflicts', function () {
    it('answers 409 email_taken when the email already belongs to a user, regardless of case or tenant', function (string $where) {
        Queue::fake();
        $tenant = $where === 'same' ? $this->tenant : makeTenant();
        User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'rina.putri@contoh.test']);

        handoff($this)->assertStatus(409)->assertJsonPath('code', 'email_taken');

        expect(Student::count())->toBe(0)->and(Payment::count())->toBe(0);
    })->with(['same', 'other']);

    it('answers 409 nik_taken when the NIK is already a student of the campus', function () {
        Queue::fake();
        Student::factory()->create(['tenant_id' => $this->tenant->id, 'nik' => '3201010101090001', 'nim' => 'LAMA1']);

        handoff($this)->assertStatus(409)->assertJsonPath('code', 'nik_taken');

        expect(User::count())->toBe(0);
    });

    it('does not treat the NIK of a student at another campus as taken', function () {
        Queue::fake();
        Student::factory()->create(['tenant_id' => makeTenant()->id, 'nik' => '3201010101090001', 'nim' => 'LAIN1']);

        handoff($this)->assertCreated();
    });

    it('leaves nothing behind after a conflict', function () {
        Queue::fake();
        User::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'rina.putri@contoh.test']);
        $before = [Student::count(), Invoice::count(), Payment::count()];

        handoff($this)->assertStatus(409);

        expect([Student::count(), Invoice::count(), Payment::count()])->toBe($before);
        Queue::assertNothingPushed();
    });
});

describe('NIM', function () {
    it('numbers students per program and batch year with the default pattern', function () {
        Queue::fake();
        $other = makeProgram($this->tenant, 'Sistem Informasi');
        FeeRate::create(['tenant_id' => $this->tenant->id, 'program_id' => $other->id, 'kind' => 'bpp', 'amount' => 100000]);
        $nims = [];

        foreach ([[$this->program, 2027], [$this->program, 2027], [$other, 2027], [$this->program, 2028], [$this->program, 2027]] as $n => [$program, $year]) {
            $response = handoff($this, [
                'admission_ref' => "REF-{$n}", 'program_id' => $program->id, 'batch_year' => $year,
                'student' => ['email' => "mhs{$n}@contoh.test", 'nik' => "320101010109000{$n}"],
                'initial_payment' => ['amount' => 100000, 'reference' => "PAY-{$n}"],
            ])->assertCreated();
            $nims[] = $response->json('nim');
        }

        expect($nims)->toBe(['27INF0001', '27INF0002', '27SIS0001', '28INF0001', '27INF0003']);
    });

    it('continues after NIMs that were entered by hand', function () {
        Queue::fake();
        Student::factory()->create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program->id, 'batch_year' => 2027, 'nim' => '27INF0009']);

        handoff($this)->assertCreated()->assertJsonPath('nim', '27INF0010');
    });

    it('follows the campus pattern setting', function () {
        Queue::fake();
        $this->tenant->update(['settings' => ['nim_pattern' => '{YYYY}.{PRODI}.{SEQ:3}']]);

        handoff($this)->assertCreated()->assertJsonPath('nim', '2027.INF.001');
    });

    it('falls back to the default pattern when the stored one is invalid', function () {
        Queue::fake();
        $this->tenant->update(['settings' => ['nim_pattern' => 'TANPA-URUTAN']]);

        handoff($this)->assertCreated()->assertJsonPath('nim', '27INF0001');
    });

    it('skips NIMs already taken by another program when the pattern has no program token', function () {
        Queue::fake();
        $this->tenant->update(['settings' => ['nim_pattern' => '{YY}{SEQ:4}']]);
        Student::factory()->create(['tenant_id' => $this->tenant->id, 'program_id' => makeProgram($this->tenant, 'Lain')->id, 'batch_year' => 2027, 'nim' => '270001']);

        handoff($this)->assertCreated()->assertJsonPath('nim', '270002');
    });

    it('never produces two students with the same NIM', function () {
        Queue::fake();
        $nims = [];

        foreach (range(1, 6) as $n) {
            $nims[] = handoff($this, [
                'admission_ref' => "SEQ-{$n}", 'student' => ['email' => "seq{$n}@contoh.test", 'nik' => "32010101010900{$n}1"],
                'initial_payment' => ['reference' => "SEQ-PAY-{$n}", 'amount' => 100000],
            ])->assertCreated()->json('nim');
        }

        expect(array_unique($nims))->toHaveCount(6)->and(Student::pluck('nim')->unique())->toHaveCount(6);
        expect(fn () => Student::create(['tenant_id' => $this->tenant->id, 'name' => 'Dobel', 'nim' => $nims[0]]))->toThrow(UniqueConstraintViolationException::class);
    });

    it('validates patterns', function (string $pattern, bool $valid) {
        expect(NimGenerator::patternError($pattern) === null)->toBe($valid);
    })->with([
        'default' => ['{YY}{PRODI}{SEQ:4}', true],
        'literal and separators' => ['NIM-{YYYY}/{PRODI}.{SEQ:5}', true],
        'sequence only' => ['{SEQ:6}', true],
        'no sequence' => ['{YY}{PRODI}', false],
        'two sequences' => ['{SEQ:2}{SEQ:2}', false],
        'unknown token' => ['{YY}{FAKULTAS}{SEQ:4}', false],
        'bad width' => ['{YY}{SEQ:0}', false],
        'spaces' => ['{YY} {SEQ:4}', false],
        'symbols' => ['{YY}%{SEQ:4}', false],
    ]);
});

describe('requests that are refused', function () {
    it('answers 401 without a token and 403 without admission.write', function () {
        $this->postJson($this->url, handoffPayload($this))->assertUnauthorized();
        $this->withToken('rusak')->postJson($this->url, handoffPayload($this))->assertUnauthorized();

        foreach ([clientToken($this->pmbServer, 'admission.read'), clientToken($this->lmsServer, 'sync.read grades.write admission.write')] as $token) {
            handoff($this, token: $token)->assertForbidden();
        }

        expect(Student::count())->toBe(0);
    });

    it('answers 403 when the Admission tenant is not configured', function () {
        config(['integration.apps.admission.tenant_id' => null]);

        handoff($this)->assertForbidden();
    });

    it('answers 404 for a program or term of another tenant or that does not exist', function () {
        $other = makeTenant();
        $foreignProgram = makeProgram($other, 'Asing');
        $foreignTerm = makeTerm($other);

        handoff($this, ['program_id' => $foreignProgram->id])->assertNotFound();
        handoff($this, ['term_id' => $foreignTerm->id])->assertNotFound();
        handoff($this, ['program_id' => 999999])->assertNotFound();
        handoff($this, ['term_id' => 999999])->assertNotFound();

        expect(User::count())->toBe(0);
    });

    it('answers 422 for invalid data', function (array $override, string $field) {
        handoff($this, $override)->assertUnprocessable()->assertJsonValidationErrors($field);
    })->with([
        'no ref' => [['admission_ref' => ''], 'admission_ref'],
        'unknown path' => [['admission_path' => 'Koneksi'], 'admission_path'],
        'bad email' => [['student' => ['email' => 'bukan-email']], 'student.email'],
        'bad gender' => [['student' => ['gender' => 'X']], 'student.gender'],
        'future birth date' => [['student' => ['birth_date' => '2999-01-01']], 'student.birth_date'],
        'zero amount' => [['initial_payment' => ['amount' => 0]], 'initial_payment.amount'],
        'bad method' => [['initial_payment' => ['method' => 'bitcoin']], 'initial_payment.method'],
        'no reference' => [['initial_payment' => ['reference' => '']], 'initial_payment.reference'],
        'bad batch year' => [['batch_year' => 1800], 'batch_year'],
        'no name' => [['student' => ['name' => '']], 'student.name'],
    ]);

    it('answers 422 when whole sections are missing', function () {
        $this->withToken($this->token)->postJson($this->url, ['admission_ref' => 'X'])->assertUnprocessable()
            ->assertJsonValidationErrors(['program_id', 'term_id', 'batch_year', 'admission_path', 'student', 'initial_payment']);
    });
});

describe('campus settings and invoice reuse', function () {
    it('saves a valid NIM pattern on the campus settings page and rejects invalid ones', function () {
        $admin = makeStaffUser($this->tenant, Role::Admin);

        Livewire::actingAs($admin)->test(Campus::class)->assertSet('nim_pattern', '{YY}{PRODI}{SEQ:4}')
            ->set('nim_pattern', '{YYYY}-{PRODI}-{SEQ:3}')->call('save')->assertHasNoErrors();

        expect($this->tenant->refresh()->setting('nim_pattern'))->toBe('{YYYY}-{PRODI}-{SEQ:3}');

        Livewire::actingAs($admin)->test(Campus::class)->set('nim_pattern', '{YY}{PRODI}')->call('save')->assertHasErrors('nim_pattern');
        Livewire::actingAs($admin)->test(Campus::class)->set('nim_pattern', '')->call('save')->assertHasErrors('nim_pattern');

        expect($this->tenant->refresh()->setting('nim_pattern'))->toBe('{YYYY}-{PRODI}-{SEQ:3}');
    });

    it('shows the pattern field on the settings page', function () {
        $this->actingAs(makeStaffUser($this->tenant, Role::Admin))->get('/settings/campus')->assertOk()->assertSee('Pola NIM mahasiswa baru');
    });

    it('issues a single-student invoice with the same rules as the bulk generator', function () {
        $student = makeStudentUser($this->tenant, [], ['program_id' => $this->program->id, 'ukt_group' => 3])->student;
        $service = app(FinanceService::class);

        $invoice = $service->issueInvoice($student, $this->term, null, today()->addDays(14), notify: false);

        expect($invoice->total)->toBe(5000000)->and($invoice->status)->toBe(InvoiceStatus::Unpaid)->and($invoice->number)->toStartWith('INV-')
            ->and($invoice->va_number)->toStartWith('8808')->and($invoice->due_on->toDateString())->toBe(today()->addDays(14)->toDateString());

        $bare = makeStudentUser($this->tenant, [], ['program_id' => makeProgram($this->tenant, 'Hukum')->id])->student;

        expect($service->issueInvoice($bare, $this->term, null, today(), notify: false))->toBeNull();
    });
});

describe('class placement', function () {
    /**
     * Kelas tingkat pertama prodi pada semester masuk, dengan anggota aktif sebanyak `$members`.
     */
    function firstLevelGroup(object $test, string $name, int $members = 0): StudyGroup
    {
        $level = GradeLevel::query()->where('tenant_id', $test->tenant->id)->orderBy('position')->first()
            ?? GradeLevel::create(['tenant_id' => $test->tenant->id, 'name' => 'Tingkat 1', 'position' => 1]);
        $group = StudyGroup::create([
            'tenant_id' => $test->tenant->id, 'academic_term_id' => $test->term->id, 'program_id' => $test->program->id,
            'grade_level_id' => $level->id, 'name' => $name,
        ]);

        for ($n = 1; $n <= $members; $n++) {
            joinGroup($group, Student::factory()->create(['tenant_id' => $test->tenant->id, 'program_id' => $test->program->id, 'nim' => "{$name}-{$n}"]));
        }

        return $group;
    }

    it('creates a first-level group without a homeroom teacher when none exists', function () {
        Queue::fake();

        $response = handoff($this)->assertCreated();

        $group = StudyGroup::firstOrFail();
        $student = Student::firstOrFail();

        expect($group->name)->toBe('INF 2027 A')->and($group->homeroom_teacher_id)->toBeNull()
            ->and($group->program_id)->toBe($this->program->id)->and($group->academic_term_id)->toBe($this->term->id)
            ->and($group->gradeLevel->name)->toBe('Tingkat 1')->and($group->gradeLevel->position)->toBe(1)
            ->and($response->json('study_group'))->toBe(['id' => $group->id, 'name' => 'INF 2027 A'])
            ->and(StudyGroupMember::where('student_id', $student->id)->whereNull('left_at')->pluck('study_group_id')->all())->toBe([$group->id]);
    });

    it('joins the existing first-level group of the program and term', function () {
        Queue::fake();
        $group = firstLevelGroup($this, 'INF-2027-A', 2);

        handoff($this)->assertCreated()->assertJsonPath('study_group', ['id' => $group->id, 'name' => 'INF-2027-A']);

        expect(StudyGroup::count())->toBe(1)->and($group->activeStudents()->count())->toBe(3);
    });

    it('picks the group with the fewest active members', function () {
        Queue::fake();
        firstLevelGroup($this, 'INF-A', 3);
        $quiet = firstLevelGroup($this, 'INF-B', 1);
        firstLevelGroup($this, 'INF-C', 2);
        StudyGroupMember::where('study_group_id', $quiet->id)->update(['left_at' => today()]);
        $leftBehind = firstLevelGroup($this, 'INF-D', 1);

        handoff($this)->assertCreated()->assertJsonPath('study_group.id', $quiet->id);

        expect($leftBehind->activeStudents()->count())->toBe(1);
    });

    it('ignores groups of another program, term, or level', function () {
        Queue::fake();
        $level = GradeLevel::create(['tenant_id' => $this->tenant->id, 'name' => 'Tingkat 1', 'position' => 1]);
        $second = GradeLevel::create(['tenant_id' => $this->tenant->id, 'name' => 'Tingkat 2', 'position' => 2]);
        StudyGroup::create(['tenant_id' => $this->tenant->id, 'academic_term_id' => $this->term->id, 'program_id' => makeProgram($this->tenant, 'Hukum')->id, 'grade_level_id' => $level->id, 'name' => 'HUK']);
        StudyGroup::create(['tenant_id' => $this->tenant->id, 'academic_term_id' => makeTerm($this->tenant, ['is_active' => false])->id, 'program_id' => $this->program->id, 'grade_level_id' => $level->id, 'name' => 'LAMA']);
        StudyGroup::create(['tenant_id' => $this->tenant->id, 'academic_term_id' => $this->term->id, 'program_id' => $this->program->id, 'grade_level_id' => $second->id, 'name' => 'INF-TK2']);
        StudyGroup::create(['tenant_id' => $this->tenant->id, 'academic_term_id' => $this->term->id, 'program_id' => $this->program->id, 'grade_level_id' => null, 'name' => 'INF-TANPA']);

        handoff($this)->assertCreated()->assertJsonPath('study_group.name', 'INF 2027 A');

        expect(StudyGroup::count())->toBe(5);
    });

    it('raises the letter when the generated name is already taken', function () {
        Queue::fake();
        GradeLevel::create(['tenant_id' => $this->tenant->id, 'name' => 'Tingkat 1', 'position' => 1]);
        $other = GradeLevel::create(['tenant_id' => $this->tenant->id, 'name' => 'Tingkat 2', 'position' => 2]);
        $this->tenant->update(['settings' => ['nim_pattern' => '{YY}{PRODI}{SEQ:4}']]);
        foreach (['INF 2027 A', 'INF 2027 B'] as $name) {
            StudyGroup::create(['tenant_id' => $this->tenant->id, 'academic_term_id' => $this->term->id, 'program_id' => $this->program->id, 'grade_level_id' => $other->id, 'name' => $name]);
        }

        handoff($this)->assertCreated()->assertJsonPath('study_group.name', 'INF 2027 C');
    });

    it('reuses the group it created for the next student of the same intake', function () {
        Queue::fake();

        handoff($this)->assertCreated();
        handoff($this, ['admission_ref' => 'REF-2', 'student' => ['email' => 'dua@contoh.test', 'nik' => '3201010101090002'], 'initial_payment' => ['reference' => 'PAY-2']])
            ->assertCreated()->assertJsonPath('study_group.name', 'INF 2027 A');

        expect(StudyGroup::count())->toBe(1)->and(StudyGroupMember::count())->toBe(2);
    });

    it('is idempotent: a repeated call neither moves the student nor adds a membership', function () {
        Queue::fake();
        $first = handoff($this)->assertCreated()->json();
        $other = firstLevelGroup($this, 'INF-SEPI', 0);
        $student = Student::firstOrFail();

        $second = handoff($this)->assertOk()->json();

        expect($second)->toBe($first)->and(StudyGroup::count())->toBe(2)
            ->and(StudyGroupMember::where('student_id', $student->id)->count())->toBe(1)->and($other->members()->count())->toBe(0);
    });

    it('reports the current group when repeated after the student was moved', function () {
        Queue::fake();
        handoff($this)->assertCreated();
        $student = Student::firstOrFail();
        $target = firstLevelGroup($this, 'INF-PINDAH', 0);
        StudyGroupMember::where('student_id', $student->id)->update(['left_at' => today()]);
        StudyGroupMember::create(['study_group_id' => $target->id, 'student_id' => $student->id, 'joined_at' => today()]);

        handoff($this)->assertOk()->assertJsonPath('study_group.id', $target->id);
    });

    it('rolls the placement back when the handoff fails', function () {
        Queue::fake();

        handoff($this, ['initial_payment' => ['amount' => 5000001]])->assertUnprocessable();

        expect(StudyGroup::count())->toBe(0)->and(StudyGroupMember::count())->toBe(0);
    });

    it('puts the new student on the roster of the classes of the group', function () {
        Queue::fake();
        $group = firstLevelGroup($this, 'INF-A', 1);
        $class = makeClass($this->tenant, ['term' => $this->term, 'program' => $this->program, 'group' => $group]);

        handoff($this)->assertCreated();

        expect(app(ClassRoster::class)->studentIds($class)->all())->toContain(Student::firstWhere('admission_ref', 'PMB-2027-000123')->id);
    });

    it('publishes roster.updated for the classes of the group', function () {
        Queue::fake();
        $group = firstLevelGroup($this, 'INF-A', 1);
        $class = makeClass($this->tenant, ['term' => $this->term, 'program' => $this->program, 'group' => $group]);
        config(['integration.lms.url' => 'http://lms.test', 'integration.lms.tenant_id' => $this->tenant->id, 'integration.lms.webhook_secret' => 'rahasia']);

        handoff($this)->assertCreated();

        expect(IntegrationEvent::where('type', 'roster.updated')->get()->pluck('payload')->all())->toContain(['class_id' => $class->id]);
    });
});

describe('class administration for BAAK', function () {
    it('lists groups without a homeroom teacher and flags new students per group', function () {
        Queue::fake();
        handoff($this)->assertCreated();
        StudyGroup::create(['tenant_id' => $this->tenant->id, 'academic_term_id' => $this->term->id, 'program_id' => $this->program->id, 'name' => 'INF-WALI', 'homeroom_teacher_id' => makeTeacherUser($this->tenant)->teacher->id]);

        foreach ([Role::Admin, Role::Curriculum] as $role) {
            Livewire::actingAs(makeStaffUser($this->tenant, $role))->test(GroupsIndex::class)
                ->assertSee('INF-WALI')->assertSee('INF 2027 A')->assertSee('1 baru')->assertSee('Tanpa dosen wali (1)')
                ->set('filter', 'tanpa-wali')->assertSee('INF 2027 A')->assertDontSee('INF-WALI');
        }
    });

    it('lets admin and curriculum set the homeroom teacher and level, but not others', function () {
        Queue::fake();
        handoff($this)->assertCreated();
        $group = StudyGroup::firstOrFail();
        $teacher = makeTeacherUser($this->tenant)->teacher;
        $level = GradeLevel::firstOrFail();

        Livewire::actingAs(makeStaffUser($this->tenant, Role::Curriculum))->test(GroupsShow::class, ['group' => $group])
            ->set('homeroom_teacher_id', (string) $teacher->id)->set('grade_level_id', (string) $level->id)->call('saveSettings')->assertHasNoErrors();

        expect($group->refresh()->homeroom_teacher_id)->toBe($teacher->id);

        Livewire::actingAs(makeStaffUser($this->tenant, Role::Principal))->test(GroupsShow::class, ['group' => $group])
            ->set('homeroom_teacher_id', '')->call('saveSettings')->assertForbidden();

        expect($group->refresh()->homeroom_teacher_id)->toBe($teacher->id);
    });

    it('moves a new student to another group of the term and publishes roster.updated for both', function () {
        Queue::fake();
        handoff($this)->assertCreated();
        $from = StudyGroup::firstOrFail();
        $to = firstLevelGroup($this, 'INF-LAIN', 1);
        $student = Student::firstWhere('admission_ref', 'PMB-2027-000123');
        $fromClass = makeClass($this->tenant, ['term' => $this->term, 'program' => $this->program, 'group' => $from]);
        $toClass = makeClass($this->tenant, ['term' => $this->term, 'program' => $this->program, 'group' => $to]);
        config(['integration.lms.url' => 'http://lms.test', 'integration.lms.tenant_id' => $this->tenant->id, 'integration.lms.webhook_secret' => 'rahasia']);

        Livewire::actingAs(makeStaffUser($this->tenant, Role::Curriculum))->test(GroupsShow::class, ['group' => $from])
            ->assertSee('Baru')->call('move', $student->id, (string) $to->id);

        expect(StudyGroupMember::where('student_id', $student->id)->whereNull('left_at')->pluck('study_group_id')->all())->toBe([$to->id])
            ->and(IntegrationEvent::where('type', 'roster.updated')->get()->pluck('payload.class_id')->all())->toContain($fromClass->id, $toClass->id);
    });

    it('refuses to move a student for roles that cannot manage groups', function () {
        Queue::fake();
        handoff($this)->assertCreated();
        $from = StudyGroup::firstOrFail();
        $to = firstLevelGroup($this, 'INF-LAIN', 0);

        Livewire::actingAs(makeTeacherUser($this->tenant))->test(GroupsShow::class, ['group' => $from])
            ->call('move', Student::firstWhere('admission_ref', 'PMB-2027-000123')->id, (string) $to->id)->assertForbidden();
    });
});

/**
 * @return array{photo: array{url: string, sha256: string, mime: string}}
 */
function photoPayload(string $bytes, string $mime = 'image/png', string $url = 'http://pmb.test/serah-terima/foto/abc123?signature=xyz'): array
{
    return ['photo' => ['url' => $url, 'sha256' => hash('sha256', $bytes), 'mime' => $mime]];
}

function pngBytes(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
}

function jpegBytes(): string
{
    return "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9";
}

describe('guardians', function () {
    $parents = [
        ['relation' => 'father', 'name' => 'Budi Santoso', 'phone' => '081111111111', 'occupation' => 'Wiraswasta', 'education' => 'S1', 'address' => 'Jl. Mawar 1'],
        ['relation' => 'mother', 'name' => 'Siti Aminah', 'phone' => '082222222222', 'occupation' => 'Guru', 'education' => 'D3', 'address' => 'Jl. Mawar 1'],
    ];

    it('creates and links the guardians without any login account', function () use ($parents) {
        Queue::fake();

        handoff($this, ['guardians' => $parents])->assertCreated();

        $student = Student::firstOrFail();
        $linked = $student->guardians()->orderBy('guardians.id')->get();

        expect($linked->pluck('relation')->all())->toBe(['Ayah', 'Ibu'])
            ->and($linked->pluck('name')->all())->toBe(['Budi Santoso', 'Siti Aminah'])
            ->and($linked[0]->only(['phone', 'occupation', 'education', 'address', 'tenant_id']))->toBe([
                'phone' => '081111111111', 'occupation' => 'Wiraswasta', 'education' => 'S1', 'address' => 'Jl. Mawar 1', 'tenant_id' => $this->tenant->id,
            ])
            ->and($linked->pluck('user_id')->filter()->all())->toBe([])->and(User::count())->toBe(1);
    });

    it('maps guardian to Wali and accepts up to three', function () {
        Queue::fake();

        handoff($this, ['guardians' => [
            ['relation' => 'father', 'name' => 'A'], ['relation' => 'mother', 'name' => 'B'], ['relation' => 'guardian', 'name' => 'C', 'phone' => ''],
        ]])->assertCreated();

        expect(Student::firstOrFail()->guardians()->pluck('relation')->sort()->values()->all())->toBe(['Ayah', 'Ibu', 'Wali']);
    });

    it('works without guardians', function () {
        Queue::fake();

        handoff($this)->assertCreated();
        handoff($this, ['admission_ref' => 'REF-2', 'guardians' => [], 'student' => ['email' => 'dua@contoh.test', 'nik' => '3201010101090002'], 'initial_payment' => ['reference' => 'PAY-2']])->assertCreated();

        expect(Guardian::count())->toBe(0);
    });

    it('shares a guardian with a sibling when relation, name, and phone match, without overwriting', function () use ($parents) {
        Queue::fake();
        handoff($this, ['guardians' => $parents])->assertCreated();

        handoff($this, [
            'admission_ref' => 'REF-ADIK', 'student' => ['email' => 'adik@contoh.test', 'nik' => '3201010101090002'], 'initial_payment' => ['reference' => 'PAY-ADIK'],
            'guardians' => [['relation' => 'father', 'name' => 'budi santoso', 'phone' => '081111111111', 'occupation' => 'Pensiunan', 'education' => 'S2', 'address' => 'Jl. Baru']],
        ])->assertCreated();

        $father = Guardian::where('relation', 'Ayah')->sole();

        expect(Guardian::count())->toBe(2)->and($father->students()->count())->toBe(2)->and($father->name)->toBe('Budi Santoso')
            ->and($father->occupation)->toBe('Wiraswasta')->and($father->address)->toBe('Jl. Mawar 1');
    });

    it('fills only the blank fields of a matched guardian', function () {
        Queue::fake();
        $existing = Guardian::create(['tenant_id' => $this->tenant->id, 'relation' => 'Ibu', 'name' => 'Siti Aminah', 'phone' => '082222222222']);

        handoff($this, ['guardians' => [['relation' => 'mother', 'name' => 'Siti Aminah', 'phone' => '082222222222', 'occupation' => 'Guru']]])->assertCreated();

        expect(Guardian::count())->toBe(1)->and($existing->refresh()->occupation)->toBe('Guru');
    });

    it('does not match guardians without a phone or of another campus', function () {
        Queue::fake();
        Guardian::create(['tenant_id' => makeTenant()->id, 'relation' => 'Ayah', 'name' => 'Budi', 'phone' => '081']);

        handoff($this, ['guardians' => [['relation' => 'father', 'name' => 'Budi', 'phone' => '081']]])->assertCreated();
        handoff($this, [
            'admission_ref' => 'REF-2', 'student' => ['email' => 'dua@contoh.test', 'nik' => '3201010101090002'], 'initial_payment' => ['reference' => 'PAY-2'],
            'guardians' => [['relation' => 'father', 'name' => 'Tanpa Telepon']],
        ])->assertCreated();
        handoff($this, [
            'admission_ref' => 'REF-3', 'student' => ['email' => 'tiga@contoh.test', 'nik' => '3201010101090003'], 'initial_payment' => ['reference' => 'PAY-3'],
            'guardians' => [['relation' => 'father', 'name' => 'Tanpa Telepon']],
        ])->assertCreated();

        expect(Guardian::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe(3);
    });

    it('rejects invalid guardians with 422 and creates nothing', function (array $guardians, string $error) {
        Queue::fake();

        handoff($this, ['guardians' => $guardians])->assertUnprocessable()->assertJsonValidationErrors($error);

        expect(Student::count())->toBe(0)->and(Guardian::count())->toBe(0);
    })->with([
        'more than three' => [[['relation' => 'father', 'name' => 'A'], ['relation' => 'mother', 'name' => 'B'], ['relation' => 'guardian', 'name' => 'C'], ['relation' => 'guardian', 'name' => 'D']], 'guardians'],
        'unknown relation' => [[['relation' => 'uncle', 'name' => 'A']], 'guardians.0.relation'],
        'repeated relation' => [[['relation' => 'father', 'name' => 'A'], ['relation' => 'father', 'name' => 'B']], 'guardians.0.relation'],
        'missing name' => [[['relation' => 'father']], 'guardians.0.name'],
    ]);

    it('does not duplicate guardians on a repeated handoff', function () use ($parents) {
        Queue::fake();
        handoff($this, ['guardians' => $parents])->assertCreated();

        handoff($this, ['guardians' => [['relation' => 'guardian', 'name' => 'Orang Lain']]])->assertOk();

        expect(Guardian::count())->toBe(2)->and(Student::firstOrFail()->guardians()->count())->toBe(2);
    });
});

describe('admission photo', function () {
    it('reports photo_status none without a photo and queues the fetch with one', function () {
        Queue::fake();

        handoff($this)->assertCreated()->assertJsonPath('photo_status', 'none');
        Queue::assertNotPushed(FetchAdmissionPhoto::class);

        $payload = photoPayload(pngBytes());
        handoff($this, ['admission_ref' => 'REF-2', 'student' => ['email' => 'dua@contoh.test', 'nik' => '3201010101090002'], 'initial_payment' => ['reference' => 'PAY-2']] + $payload)
            ->assertCreated()->assertJsonPath('photo_status', 'queued');

        Queue::assertPushed(FetchAdmissionPhoto::class, fn (FetchAdmissionPhoto $job) => $job->studentId === Student::firstWhere('admission_ref', 'REF-2')->id
            && $job->url === $payload['photo']['url'] && $job->sha256 === $payload['photo']['sha256'] && $job->mime === 'image/png');
    });

    it('downloads, validates, and stores the photo on the private disk', function (string $mime, string $extension) {
        $bytes = $extension === 'png' ? pngBytes() : jpegBytes();
        Notification::fake();
        Http::fake(['pmb.test/*' => Http::response($bytes, 200, ['Content-Type' => $mime])]);

        handoff($this, photoPayload($bytes, $mime))->assertCreated()->assertJsonPath('photo_status', 'queued');

        $student = Student::firstOrFail();

        expect($student->photo_path)->toBe("students/photos/{$this->tenant->id}/{$student->id}.{$extension}");
        Storage::disk('local')->assertExists($student->photo_path);
        expect(Storage::disk('local')->get($student->photo_path))->toBe($bytes)
            ->and(AuditLog::where('action', 'admission.photo_saved')->count())->toBe(1);
        Http::assertSent(fn (Request $request) => $request->url() === 'http://pmb.test/serah-terima/foto/abc123?signature=xyz');
    })->with([
        'png' => ['image/png', 'png'],
        'jpeg' => ['image/jpeg', 'jpg'],
    ]);

    it('records a failure in the audit log and keeps the handoff when the photo is unusable', function (string $case) {
        Notification::fake();
        $bytes = pngBytes();
        $payload = photoPayload($bytes);

        if ($case === 'hash') {
            $payload['photo']['sha256'] = str_repeat('a', 64);
        } elseif ($case === 'content') {
            $payload = photoPayload($bytes, 'image/jpeg');
        } elseif ($case === 'size') {
            $bytes .= str_repeat('x', FetchAdmissionPhoto::MAX_BYTES);
            $payload = photoPayload($bytes);
        }

        Http::fake(['pmb.test/*' => match ($case) {
            'expired' => Http::response('', 403),
            'gone' => Http::response('', 404),
            'redirect' => Http::response('', 302, ['Location' => 'http://intranet.test/secret']),
            'unavailable' => Http::response('', 503),
            default => Http::response($bytes, 200),
        }]);

        handoff($this, $payload)->assertCreated();

        expect(Student::firstOrFail()->photo_path)->toBeNull()->and(User::count())->toBe(1)->and(Invoice::count())->toBe(1)
            ->and(AuditLog::where('action', 'admission.photo_failed')->count())->toBe(1);
        Http::assertSentCount(1);
    })->with(['hash', 'content', 'size', 'expired', 'gone', 'redirect', 'unavailable']);

    it('records a failure when the server cannot be reached', function () {
        Notification::fake();
        Http::fake(['pmb.test/*' => fn () => throw new ConnectionException('timeout')]);

        handoff($this, photoPayload(pngBytes()))->assertCreated();

        expect(Student::firstOrFail()->photo_path)->toBeNull()->and(AuditLog::where('action', 'admission.photo_failed')->firstOrFail()->new_values['reason'])->toContain('timeout');
    });

    it('retries transient failures through the queue and records the last failure when it gives up', function () {
        Notification::fake();
        Http::fake(['pmb.test/*' => Http::response('', 503)]);
        handoff($this, photoPayload(pngBytes()))->assertCreated();
        $student = Student::firstOrFail();
        AuditLog::query()->delete();

        $job = new FetchAdmissionPhoto($student->id, 'http://pmb.test/serah-terima/foto/abc123?signature=xyz', hash('sha256', pngBytes()), 'image/png');
        $job->setJob(Mockery::mock(QueueJob::class));

        expect(fn () => $job->handle(app(AuditLogger::class)))->toThrow(RuntimeException::class, 'HTTP 503')
            ->and(AuditLog::count())->toBe(0)->and($job->tries)->toBe(5)->and($job->backoff())->toBe([60, 300, 900, 3600]);

        $job->failed(new RuntimeException('HTTP 503'));

        expect(AuditLog::where('action', 'admission.photo_failed')->count())->toBe(1);
    });

    it('stores the photo on a later retry once the server answers', function () {
        Notification::fake();
        Http::fake(['pmb.test/*' => Http::sequence()->push('', 503)->push(pngBytes(), 200)]);
        handoff($this, photoPayload(pngBytes()))->assertCreated();
        $student = Student::firstOrFail();

        (new FetchAdmissionPhoto($student->id, 'http://pmb.test/serah-terima/foto/abc123?signature=xyz', hash('sha256', pngBytes()), 'image/png'))->handle(app(AuditLogger::class));

        expect($student->refresh()->photo_path)->not->toBeNull();
    });

    it('refuses a photo URL outside Admission, a bad hash format, and an unsupported mime', function (array $photo, string $error) {
        Queue::fake();

        handoff($this, ['photo' => $photo])->assertUnprocessable()->assertJsonValidationErrors($error);

        expect(Student::count())->toBe(0);
        Queue::assertNotPushed(FetchAdmissionPhoto::class);
    })->with([
        'other host' => [['url' => 'http://intranet.test/secret.png', 'sha256' => str_repeat('a', 64), 'mime' => 'image/png'], 'photo.url'],
        'other port' => [['url' => 'http://pmb.test:8080/foto', 'sha256' => str_repeat('a', 64), 'mime' => 'image/png'], 'photo.url'],
        'not a url' => [['url' => 'file:///etc/passwd', 'sha256' => str_repeat('a', 64), 'mime' => 'image/png'], 'photo.url'],
        'bad hash' => [['url' => 'http://pmb.test/foto', 'sha256' => 'xyz', 'mime' => 'image/png'], 'photo.sha256'],
        'gif' => [['url' => 'http://pmb.test/foto', 'sha256' => str_repeat('a', 64), 'mime' => 'image/gif'], 'photo.mime'],
        'incomplete' => [['url' => 'http://pmb.test/foto'], 'photo.mime'],
    ]);

    it('does not download again when the photo is already stored', function () {
        Notification::fake();
        Http::fake(['pmb.test/*' => Http::response(pngBytes(), 200)]);
        handoff($this, photoPayload(pngBytes()))->assertCreated();

        handoff($this, photoPayload(pngBytes()))->assertOk()->assertJsonPath('photo_status', 'none');

        Http::assertSentCount(1);
    });

    it('queues the download again on a repeated call with a fresh URL after a failure', function () {
        Notification::fake();
        Http::fake(['pmb.test/*' => Http::sequence()->push('', 403)->push(pngBytes(), 200)]);
        handoff($this, photoPayload(pngBytes()))->assertCreated();
        expect(Student::firstOrFail()->photo_path)->toBeNull();

        handoff($this, photoPayload(pngBytes(), 'image/png', 'http://pmb.test/serah-terima/foto/baru?signature=segar'))->assertOk()->assertJsonPath('photo_status', 'queued');

        expect(Student::firstOrFail()->photo_path)->not->toBeNull()->and(Student::count())->toBe(1);
    });

    it('skips a job whose student already has a photo or no longer exists', function () {
        Http::fake();
        $job = new FetchAdmissionPhoto(999999, 'http://pmb.test/foto', hash('sha256', 'x'), 'image/png');

        $job->handle(app(AuditLogger::class));

        Http::assertNothingSent();
    });
});

describe('fee model', function () {
    it('answers 422 ukt_group_required in UKT mode when the group is missing and creates nothing', function () {
        Queue::fake();

        handoff($this, ['ukt_group' => null])->assertUnprocessable()->assertJsonPath('code', 'ukt_group_required');

        expect(User::count())->toBe(0)->and(Student::count())->toBe(0)->and(Invoice::count())->toBe(0)->and(StudyGroup::count())->toBe(0);
    });

    it('treats a campus without the setting as UKT mode', function () {
        Queue::fake();
        $this->tenant->update(['settings' => []]);

        handoff($this, ['ukt_group' => null])->assertUnprocessable()->assertJsonPath('code', 'ukt_group_required');
    });

    it('bills only the BPP in flat mode, accepts a null group, and ignores a given one', function (?int $group) {
        Queue::fake();
        $this->tenant->update(['settings' => ['fee_model' => 'flat']]);

        handoff($this, ['ukt_group' => $group, 'initial_payment' => ['amount' => 500000]])->assertCreated()
            ->assertJsonPath('invoice.total', 500000)->assertJsonPath('invoice.status', 'paid');

        $invoice = Invoice::firstOrFail();

        expect($invoice->items()->pluck('amount', 'description')->all())->toBe(['BPP' => 500000])
            ->and($invoice->academic_term_id)->toBe($this->term->id);
    })->with([null, 3]);

    it('leaves a flat-mode invoice partial when the first payment is less', function () {
        Queue::fake();
        $this->tenant->update(['settings' => ['fee_model' => 'flat']]);

        handoff($this, ['ukt_group' => null, 'initial_payment' => ['amount' => 200000]])->assertCreated()
            ->assertJsonPath('invoice.status', 'partial')->assertJsonPath('invoice.total', 500000);
    });

    it('rejects a flat-mode handoff for a program without BPP, even if it has UKT rates', function () {
        Queue::fake();
        $this->tenant->update(['settings' => ['fee_model' => 'flat']]);
        $ukt = makeProgram($this->tenant, 'Hukum');
        FeeRate::create(['tenant_id' => $this->tenant->id, 'program_id' => $ukt->id, 'kind' => 'ukt', 'ukt_group' => null, 'amount' => 3000000]);

        handoff($this, ['program_id' => $ukt->id, 'ukt_group' => null])->assertUnprocessable()->assertJsonPath('code', 'fee_rate_missing')
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'tarif BPP'));

        expect(Student::count())->toBe(0);
    });

    it('keeps UKT-mode invoices (group rate plus BPP) unchanged', function () {
        Queue::fake();
        $this->tenant->update(['settings' => ['fee_model' => 'ukt']]);

        handoff($this)->assertCreated()->assertJsonPath('invoice.total', 5000000);

        expect(Invoice::firstOrFail()->items()->pluck('amount', 'description')->all())->toBe(['UKT' => 4500000, 'BPP' => 500000]);
    });

    it('does not apply the fee-model check to a repeated handoff', function () {
        Queue::fake();
        handoff($this)->assertCreated();
        $this->tenant->update(['settings' => ['fee_model' => 'ukt']]);

        handoff($this, ['ukt_group' => null])->assertOk();
    });

    it('lets admin switch the fee model on the campus settings page and rejects unknown values', function () {
        $admin = makeStaffUser($this->tenant, Role::Admin);

        Livewire::actingAs($admin)->test(Campus::class)->assertSet('fee_model', 'ukt')
            ->set('fee_model', 'flat')->call('save')->assertHasNoErrors();

        expect($this->tenant->refresh()->feeModel())->toBe('flat');

        Livewire::actingAs($admin)->test(Campus::class)->assertSet('fee_model', 'flat')->set('fee_model', 'bebas')->call('save')->assertHasErrors('fee_model');

        expect($this->tenant->refresh()->feeModel())->toBe('flat');
        $this->actingAs($admin)->get('/settings/campus')->assertOk()->assertSee('Model biaya');
    });

    it('rateFor and bulk generation follow the fee model', function () {
        $student = makeStudentUser($this->tenant, [], ['program_id' => $this->program->id, 'ukt_group' => 3, 'batch_year' => 2027])->student;
        $finance = app(FinanceService::class);

        expect($finance->rateFor($student, 'ukt'))->toBe(4500000)->and($finance->rateFor($student, 'bpp'))->toBe(500000);

        $this->tenant->update(['settings' => ['fee_model' => 'flat']]);
        $student->refresh();

        expect($finance->rateFor($student, 'ukt'))->toBeNull()->and($finance->rateFor($student, 'bpp'))->toBe(500000);

        $finance->generate($this->term, makeStaffUser($this->tenant, Role::Finance), today()->addWeek());

        expect(Invoice::where('student_id', $student->id)->firstOrFail()->items()->pluck('amount', 'description')->all())->toBe(['BPP' => 500000]);
    });
});
