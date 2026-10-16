<?php

use App\Enums\ConversionStatus;
use App\Enums\GraduationStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Enums\ThesisStatus;
use App\Models\CreditConversion;
use App\Models\CreditConversionItem;
use App\Models\GraduationDecision;
use App\Models\GraduationPeriod;
use App\Models\GraduationRegistration;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Thesis;
use App\Models\Yudisium;
use App\Services\GraduationService;
use App\Services\YudisiumService;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->tenant = makeTenant(['settings' => [
        'graduation_min_credits' => 9, 'graduation_min_gpa' => 2.0, 'graduation_max_d_percent' => 25,
        'thesis_required_for_graduation' => false,
    ]]);
    $this->term = makeTerm($this->tenant);
    $this->student = makeStudentUser($this->tenant, [], ['entry_date' => today()->subYears(4)->toDateString()])->student;
    $this->service = app(YudisiumService::class);
    $this->admin = makeStaffUser($this->tenant, Role::Admin);
});

/**
 * Beri mahasiswa nilai terkunci pada kuliah-kuliah 3 SKS dengan nilai angka tertentu.
 *
 * @param  array<int, float>  $totals
 */
function transcriptOf(object $test, Student $student, array $totals): void
{
    foreach ($totals as $total) {
        $class = makeClass($test->tenant, ['term' => $test->term, 'credits' => 3]);
        giveGrade($class, $student, $total);
        lockGrades($class);
    }
}

function completeThesis(object $test, Student $student, string $letter = 'A'): Thesis
{
    return Thesis::create([
        'tenant_id' => $test->tenant->id, 'student_id' => $student->id, 'academic_term_id' => $test->term->id, 'title' => 'Sistem Presensi',
        'status' => ThesisStatus::Completed, 'completed_at' => now(), 'final_letter' => $letter, 'final_score' => 85,
    ]);
}

/**
 * @return array<string, bool> kunci cek => terpenuhi
 */
function checkResults(array $result): array
{
    return collect($result['checks'])->pluck('met', 'key')->all();
}

describe('evaluate', function () {
    it('passes a student who meets every requirement and assigns a predicate', function () {
        transcriptOf($this, $this->student, [90, 88, 85]);

        $result = $this->service->evaluate($this->student);

        expect($result['eligible'])->toBeTrue()
            ->and($result['credits'])->toBe(9)
            ->and($result['gpa'])->toBe(4.0)
            ->and($result['e_count'])->toBe(0)
            ->and($result['predicate'])->not->toBeNull()
            ->and(collect($result['checks'])->every(fn ($check) => $check['met']))->toBeTrue();
    });

    it('fails the credits check below the campus minimum', function () {
        transcriptOf($this, $this->student, [90, 88]);

        $result = $this->service->evaluate($this->student);

        expect($result['eligible'])->toBeFalse()->and(checkResults($result)['credits'])->toBeFalse()->and($result['predicate'])->toBeNull();
    });

    it('fails the GPA check below the minimum', function () {
        $this->tenant->update(['settings' => ['graduation_min_credits' => 9, 'graduation_min_gpa' => 3.5, 'thesis_required_for_graduation' => false]]);
        transcriptOf($this, $this->student->refresh(), [60, 60, 60]);

        $result = $this->service->evaluate($this->student->refresh());

        expect(checkResults($result)['gpa'])->toBeFalse()->and($result['eligible'])->toBeFalse();
    });

    it('fails when any course is E, even if credits and GPA are enough', function () {
        transcriptOf($this, $this->student, [90, 90, 90, 90, 10]);

        $result = $this->service->evaluate($this->student);

        expect($result['e_count'])->toBe(1)->and(checkResults($result)['no_e'])->toBeFalse()->and(checkResults($result)['credits'])->toBeTrue()->and($result['eligible'])->toBeFalse();
    });

    it('limits D grades to the configured percentage of passed credits', function () {
        transcriptOf($this, $this->student, [90, 90, 90, 55]);
        $ok = $this->service->evaluate($this->student);

        expect($ok['d_percent'])->toBe(25.0)->and(checkResults($ok)['max_d'])->toBeTrue();

        transcriptOf($this, $this->student, [55]);
        $bad = $this->service->evaluate($this->student);

        expect(checkResults($bad)['max_d'])->toBeFalse()->and($bad['eligible'])->toBeFalse();
    });

    it('requires a completed thesis when the campus asks for it', function () {
        $this->tenant->update(['settings' => ['graduation_min_credits' => 9, 'graduation_min_gpa' => 2.0, 'thesis_required_for_graduation' => true]]);
        transcriptOf($this, $this->student->refresh(), [90, 90, 90]);

        $without = $this->service->evaluate($this->student->refresh());
        expect(checkResults($without)['thesis'])->toBeFalse()->and($without['eligible'])->toBeFalse();

        Thesis::create(['tenant_id' => $this->tenant->id, 'student_id' => $this->student->id, 'academic_term_id' => $this->term->id, 'title' => 'Belum selesai', 'status' => ThesisStatus::DefenseScheduled]);
        expect(checkResults($this->service->evaluate($this->student->refresh()))['thesis'])->toBeFalse();

        completeThesis($this, $this->student);
        expect(checkResults($this->service->evaluate($this->student->refresh()))['thesis'])->toBeTrue();
    });

    it('does not list a thesis check when the campus does not require one', function () {
        transcriptOf($this, $this->student, [90, 90, 90]);

        expect(checkResults($this->service->evaluate($this->student)))->not->toHaveKey('thesis');
    });

    it('fails with an unpaid overdue invoice, but not when exempted, paid, or not yet due', function () {
        transcriptOf($this, $this->student, [90, 90, 90]);
        $invoice = Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'student_id' => $this->student->id, 'academic_term_id' => $this->term->id, 'due_on' => today()->subDay(), 'status' => 'unpaid']);

        expect(checkResults($this->service->evaluate($this->student))['arrears'])->toBeFalse();

        $invoice->update(['krs_exempt' => true]);
        expect(checkResults($this->service->evaluate($this->student))['arrears'])->toBeTrue();

        $invoice->update(['krs_exempt' => false, 'status' => 'partial']);
        expect(checkResults($this->service->evaluate($this->student))['arrears'])->toBeFalse();

        $invoice->update(['status' => 'paid']);
        expect(checkResults($this->service->evaluate($this->student))['arrears'])->toBeTrue();

        $invoice->update(['status' => 'unpaid', 'due_on' => today()->addDay()]);
        expect(checkResults($this->service->evaluate($this->student))['arrears'])->toBeTrue();
    });

    it('requires an active (or already graduated) student', function (StudentStatus $status, bool $met) {
        transcriptOf($this, $this->student, [90, 90, 90]);
        $this->student->update(['status' => $status]);

        expect(checkResults($this->service->evaluate($this->student->refresh()))['status'])->toBe($met);
    })->with([[StudentStatus::Active, true], [StudentStatus::Graduated, true], [StudentStatus::Leave, false], [StudentStatus::Dropped, false], [StudentStatus::Inactive, false]]);

    it('counts only locked grades', function () {
        $class = makeClass($this->tenant, ['term' => $this->term, 'credits' => 3]);
        giveGrade($class, $this->student, 90);

        expect($this->service->evaluate($this->student)['credits'])->toBe(0);
    });

    it('counts approved credit conversions toward passed credits and ignores pending ones', function () {
        $subject = Subject::create(['tenant_id' => $this->tenant->id, 'program_id' => makeProgram($this->tenant)->id, 'name' => 'Konversi', 'code' => 'KV1', 'credits' => 3, 'semester' => 1]);
        $conversion = CreditConversion::create(['tenant_id' => $this->tenant->id, 'student_id' => $this->student->id, 'source' => 'internship', 'description' => 'Magang MBKM', 'status' => ConversionStatus::Submitted]);
        CreditConversionItem::create(['credit_conversion_id' => $conversion->id, 'subject_id' => $subject->id, 'credits' => 3, 'letter' => 'A', 'points' => 4]);

        expect($this->service->evaluate($this->student)['credits'])->toBe(0);

        $conversion->update(['status' => ConversionStatus::Approved]);

        expect($this->service->evaluate($this->student)['credits'])->toBe(3);
    });
});

describe('predicate', function () {
    it('picks cum laude, very satisfactory, or satisfactory from GPA, D grades, and study length', function (float $gpa, int $dCredits, float $years, string $expected) {
        expect($this->service->predicate($gpa, $dCredits, $years, $this->student))->toBe($expected);
    })->with([
        'cum laude' => [3.51, 0, 4.5, 'Dengan Pujian'],
        'too slow' => [3.8, 0, 4.6, 'Sangat Memuaskan'],
        'has a D' => [3.8, 3, 4.0, 'Sangat Memuaskan'],
        'just below cum laude' => [3.50, 0, 4.0, 'Sangat Memuaskan'],
        'very satisfactory edge' => [2.76, 3, 5.0, 'Sangat Memuaskan'],
        'satisfactory' => [2.75, 0, 3.0, 'Memuaskan'],
    ]);

    it('uses the thresholds configured by the campus', function () {
        $this->tenant->update(['settings' => ['predicate_cum_laude_gpa' => 3.9, 'predicate_very_satisfactory_gpa' => 3.0]]);
        $student = $this->student->refresh();

        expect($this->service->predicate(3.7, 0, 4.0, $student))->toBe('Sangat Memuaskan')
            ->and($this->service->predicate(2.9, 0, 4.0, $student))->toBe('Memuaskan');
    });
});

describe('candidates', function () {
    it('lists active students with a completed thesis and no decision yet', function () {
        $done = makeStudentUser($this->tenant)->student;
        $decided = makeStudentUser($this->tenant)->student;
        $inactive = makeStudentUser($this->tenant, [], ['status' => 'cuti'])->student;
        $noThesis = makeStudentUser($this->tenant)->student;
        $this->tenant->update(['settings' => ['thesis_required_for_graduation' => true]]);

        foreach ([$done, $decided, $inactive] as $student) {
            completeThesis($this, $student);
        }

        $yudisium = Yudisium::create(['tenant_id' => $this->tenant->id, 'name' => 'Yudisium I', 'held_on' => today()]);
        GraduationDecision::create(['tenant_id' => $this->tenant->id, 'yudisium_id' => $yudisium->id, 'student_id' => $decided->id, 'credits' => 9, 'gpa' => 3, 'predicate' => 'Memuaskan', 'checks' => []]);

        $ids = $this->service->candidates($this->tenant->refresh())->pluck('id')->all();

        expect($ids)->toBe([$done->id])->and($ids)->not->toContain($noThesis->id);
    });

    it('falls back to the batch year when no thesis is required', function () {
        $old = makeStudentUser($this->tenant, [], ['batch_year' => now()->year - 4])->student;
        $new = makeStudentUser($this->tenant, [], ['batch_year' => now()->year])->student;

        $ids = $this->service->candidates($this->tenant)->pluck('id')->all();

        expect($ids)->toContain($old->id)->not->toContain($new->id);
    });
});

describe('finalize', function () {
    beforeEach(function () {
        $this->yudisium = Yudisium::create(['tenant_id' => $this->tenant->id, 'name' => 'Yudisium I', 'held_on' => today()]);
        transcriptOf($this, $this->student, [90, 88, 85]);
        $this->failing = makeStudentUser($this->tenant)->student;
    });

    it('graduates only the eligible students and records an immutable decision snapshot', function () {
        $count = $this->service->finalize($this->yudisium, $this->admin, [$this->student->id, $this->failing->id], 'YUD/001');

        $decision = GraduationDecision::where('student_id', $this->student->id)->firstOrFail();

        expect($count)->toBe(1)
            ->and($this->student->refresh()->status)->toBe(StudentStatus::Graduated)
            ->and($this->student->graduated_on->toDateString())->toBe(today()->toDateString())
            ->and($this->student->graduation_predicate)->toBe($decision->predicate)
            ->and($decision->credits)->toBe(9)
            ->and((float) $decision->gpa)->toBe(4.0)
            ->and($decision->checks)->toBeArray()->not->toBeEmpty()
            ->and($this->failing->refresh()->status)->toBe(StudentStatus::Active)
            ->and(GraduationDecision::where('student_id', $this->failing->id)->exists())->toBeFalse()
            ->and($this->yudisium->refresh()->isFinalized())->toBeTrue()
            ->and($this->yudisium->number)->toBe('YUD/001');
    });

    it('refuses when nobody selected qualifies and leaves the yudisium open', function () {
        expect(fn () => $this->service->finalize($this->yudisium, $this->admin, [$this->failing->id], null))->toThrow(ValidationException::class, 'Tidak ada mahasiswa');

        expect($this->yudisium->refresh()->isFinalized())->toBeFalse();
    });

    it('cannot be finalized twice', function () {
        $this->service->finalize($this->yudisium, $this->admin, [$this->student->id], null);

        expect(fn () => $this->service->finalize($this->yudisium->refresh(), $this->admin, [$this->student->id], null))->toThrow(ValidationException::class, 'sudah ditetapkan');
    });

    it('is limited to admin, principal, and curriculum roles', function () {
        foreach ([Role::Teacher, Role::Student, Role::Finance, Role::Duty] as $role) {
            expect(fn () => $this->service->finalize($this->yudisium, makeStaffUser($this->tenant, $role), [$this->student->id], null))->toThrow(HttpException::class);
        }

        foreach ([Role::Admin, Role::Principal, Role::Curriculum] as $role) {
            expect($this->service->isManager(makeStaffUser($this->tenant, $role)))->toBeTrue();
        }

        expect($this->student->refresh()->status)->toBe(StudentStatus::Active);
    });

    it('skips students who are not active at finalization', function () {
        $this->student->update(['status' => StudentStatus::Leave]);

        expect(fn () => $this->service->finalize($this->yudisium, $this->admin, [$this->student->id], null))->toThrow(ValidationException::class);
    });
});

describe('graduation eligibility and registration', function () {
    beforeEach(function () {
        $this->period = GraduationPeriod::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Wisuda I', 'registration_opens_on' => today()->subWeek(), 'registration_closes_on' => today()->addWeek(),
            'ceremony_on' => today()->addMonth(), 'quota' => 5, 'is_published' => true,
        ]);
        $this->graduation = app(GraduationService::class);
        $this->payload = ['thesis_title' => 'Judul', 'defense_date' => today()->subMonth()->toDateString()];
    });

    function decide(object $test, Student $student): void
    {
        $yudisium = Yudisium::firstOrCreate(['tenant_id' => $test->tenant->id, 'name' => 'Y'], ['held_on' => today(), 'status' => 'draft']);
        GraduationDecision::create(['tenant_id' => $test->tenant->id, 'yudisium_id' => $yudisium->id, 'student_id' => $student->id, 'credits' => 9, 'gpa' => 3.5, 'predicate' => 'Memuaskan', 'checks' => []]);
    }

    it('takes credits and GPA from the transcript and compares them with the campus minimums', function () {
        expect($this->graduation->eligibility($this->student))->toMatchArray(['credits' => 0, 'gpa' => null, 'has_grades' => false, 'eligible' => false]);

        transcriptOf($this, $this->student, [90, 90]);
        expect($this->graduation->eligibility($this->student))->toMatchArray(['credits' => 6, 'meets_credits' => false, 'meets_gpa' => true, 'eligible' => false]);

        transcriptOf($this, $this->student, [90]);
        expect($this->graduation->eligibility($this->student))->toMatchArray(['credits' => 9, 'meets_credits' => true, 'eligible' => true, 'min_credits' => 9]);
    });

    it('requires a yudisium decision before registering', function () {
        transcriptOf($this, $this->student, [90, 90, 90]);

        expect(fn () => $this->graduation->register($this->student, $this->period, $this->payload))->toThrow(ValidationException::class, 'belum dinyatakan lulus yudisium');
    });

    it('registers an eligible student with a snapshot of credits and GPA from the transcript', function () {
        transcriptOf($this, $this->student, [90, 80, 70]);
        decide($this, $this->student);

        $registration = $this->graduation->register($this->student, $this->period, $this->payload + ['gpa' => 1.0, 'credits_earned' => 1]);

        expect($registration->status)->toBe(GraduationStatus::Submitted)
            ->and($registration->credits_earned)->toBe(9)
            ->and((float) $registration->gpa)->toBe(3.67);
    });

    it('takes the thesis title and defense date from the completed thesis', function () {
        transcriptOf($this, $this->student, [90, 90, 90]);
        decide($this, $this->student);
        completeThesis($this, $this->student);

        $registration = $this->graduation->register($this->student, $this->period, ['thesis_title' => 'Diabaikan', 'defense_date' => '2000-01-01']);

        expect($registration->thesis_title)->toBe('Sistem Presensi')->and($registration->defense_date->toDateString())->toBe(today()->toDateString());
    });

    it('blocks duplicates, closed periods, and students without enough credits or GPA', function () {
        transcriptOf($this, $this->student, [90, 90, 90]);
        decide($this, $this->student);
        $this->graduation->register($this->student, $this->period, $this->payload);

        expect(fn () => $this->graduation->register($this->student, $this->period, $this->payload))->toThrow(ValidationException::class, 'sudah terdaftar');

        $this->period->update(['registration_closes_on' => today()->subDay()]);
        $other = makeStudentUser($this->tenant)->student;
        decide($this, $other);

        expect(fn () => $this->graduation->register($other, $this->period, $this->payload))->toThrow(ValidationException::class, 'tidak dibuka');

        $this->period->update(['registration_closes_on' => today()->addWeek()]);
        expect(fn () => $this->graduation->register($other, $this->period, $this->payload))->toThrow(ValidationException::class, 'Belum ada nilai');

        transcriptOf($this, $other, [90]);
        expect(fn () => $this->graduation->register($other, $this->period->refresh(), $this->payload))->toThrow(ValidationException::class, 'SKS lulusmu');
    });

    it('enforces the quota for new registrations but lets a rejected student register again', function () {
        $this->period->update(['quota' => 1]);
        transcriptOf($this, $this->student, [90, 90, 90]);
        decide($this, $this->student);
        $first = $this->graduation->register($this->student, $this->period, $this->payload);

        $other = makeStudentUser($this->tenant)->student;
        transcriptOf($this, $other, [90, 90, 90]);
        decide($this, $other);

        expect(fn () => $this->graduation->register($other, $this->period, $this->payload))->toThrow(ValidationException::class, 'Kuota');

        $first->update(['status' => GraduationStatus::Rejected, 'rejection_reason' => 'Berkas kurang']);
        $again = $this->graduation->register($this->student, $this->period, $this->payload);

        expect($again->id)->toBe($first->id)->and($again->status)->toBe(GraduationStatus::Submitted)->and($again->rejection_reason)->toBeNull()
            ->and(GraduationRegistration::count())->toBe(1);
    });

    it('only accepts active or already graduated students', function () {
        transcriptOf($this, $this->student, [90, 90, 90]);
        decide($this, $this->student);
        $this->student->update(['status' => StudentStatus::Leave]);

        expect(fn () => $this->graduation->register($this->student->refresh(), $this->period, $this->payload))->toThrow(ValidationException::class, 'Hanya mahasiswa aktif');
    });
});
