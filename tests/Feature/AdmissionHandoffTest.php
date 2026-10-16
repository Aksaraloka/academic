<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Jobs\SendPasswordSetupLink;
use App\Livewire\Settings\Campus;
use App\Models\AuditLog;
use App\Models\FeeRate;
use App\Models\Invoice;
use App\Models\OauthClient;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Notifications\AcademicNotice;
use App\Services\FinanceService;
use App\Services\NimGenerator;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
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

        expect($response->json())->toBe([
            'user_id' => $user->id, 'student_id' => $student->id, 'nim' => '27INF0001',
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

    it('falls back to the general UKT rate without a group and accepts missing optional data', function () {
        Queue::fake();

        handoff($this, ['ukt_group' => null, 'student' => ['phone' => null, 'nik' => null], 'initial_payment' => ['amount' => 4500000]])->assertCreated()
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
