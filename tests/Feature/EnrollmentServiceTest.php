<?php

use App\Enums\AcademicEventType;
use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\AcademicEvent;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudyGroupMember;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\TimeSlot;
use App\Services\ClassRoster;
use App\Services\EnrollmentService;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->tenant = makeTenant();
    $this->term = makeTerm($this->tenant);
    $this->program = makeProgram($this->tenant);
    $this->user = makeStudentUser($this->tenant);
    $this->student = $this->user->student;
    $this->service = app(EnrollmentService::class);
});

/**
 * Selesaikan satu mata kuliah di semester sebelumnya dengan nilai terkunci.
 */
function passPreviousSubject(object $test, Subject $subject, float $total): void
{
    $previous = makeTerm($test->tenant, ['name' => '2025/2026', 'semester' => 'Genap', 'is_active' => false,
        'starts_at' => today()->subYear()->toDateString(), 'ends_at' => today()->subMonths(6)->toDateString()]);
    $class = makeClass($test->tenant, ['term' => $previous, 'program' => $test->program, 'subject' => $subject]);
    giveGrade($class, $test->student, $total);
    lockGrades($class);
}

function offer(object $test, int $credits = 3, array $options = []): TeachingAssignment
{
    return makeClass($test->tenant, $options + ['term' => $test->term, 'program' => $test->program, 'credits' => $credits]);
}

it('refuses to edit the KRS outside the enrollment window and accepts it inside', function () {
    $class = offer($this);
    $enrollment = $this->service->start($this->student, $this->term);

    expect(fn () => $this->service->saveSelection($enrollment, [$class->id]))
        ->toThrow(ValidationException::class, 'Masa pengisian KRS sedang ditutup');

    openEnrollmentWindow($this->term);
    $this->service->saveSelection($enrollment, [$class->id]);

    expect($enrollment->assignments()->pluck('teaching_assignments.id')->all())->toBe([$class->id]);
});

it('refuses a window that already ended or has not started', function () {
    $class = offer($this);
    $enrollment = $this->service->start($this->student, $this->term);

    AcademicEvent::create([
        'tenant_id' => $this->tenant->id, 'academic_term_id' => $this->term->id, 'type' => AcademicEventType::Enrollment,
        'starts_on' => today()->subWeeks(3)->toDateString(), 'ends_on' => today()->subWeeks(2)->toDateString(),
    ]);

    expect(fn () => $this->service->saveSelection($enrollment, [$class->id]))->toThrow(ValidationException::class, 'ditutup');
});

it('refuses editing for a locked term', function () {
    openEnrollmentWindow($this->term);
    $enrollment = $this->service->start($this->student, $this->term);
    $this->term->update(['is_locked' => true]);

    expect(fn () => $this->service->saveSelection($enrollment->refresh(), []))->toThrow(ValidationException::class, 'ditutup');
});

it('only lets active students edit their KRS', function () {
    openEnrollmentWindow($this->term);
    $enrollment = $this->service->start($this->student, $this->term);
    $this->student->update(['status' => StudentStatus::Leave]);

    expect(fn () => $this->service->saveSelection($enrollment->refresh(), []))->toThrow(ValidationException::class, 'Status akademikmu');
});

describe('credit limit', function () {
    it('allows 20 credits to a student without previous grades', function () {
        openEnrollmentWindow($this->term);
        expect($this->service->maxCredits($this->student, $this->term))->toBe(EnrollmentService::FIRST_SEMESTER_CREDITS);

        $enrollment = $this->service->start($this->student, $this->term);
        $classes = collect(range(1, 7))->map(fn () => offer($this, 3));

        expect(fn () => $this->service->saveSelection($enrollment, $classes->pluck('id')->all()))
            ->toThrow(ValidationException::class, 'melebihi batas 20 SKS');

        $this->service->saveSelection($enrollment, $classes->take(6)->pluck('id')->all());
        expect($enrollment->assignments()->count())->toBe(6);
    });

    it('derives the limit from the IPS of the previous term', function (float $score, int $expected) {
        passPreviousSubject($this, Subject::create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program->id, 'name' => 'Lama', 'code' => 'LAMA', 'credits' => 3, 'semester' => 1]), $score);

        expect($this->service->maxCredits($this->student, $this->term))->toBe($expected);
    })->with([
        'IPS 4.0 -> 24' => [90.0, 24],
        'IPS 3.0 -> 24' => [70.0, 21 + 3],
        'IPS 2.0 -> 18' => [60.0, 18],
        'IPS 1.0 -> 15' => [50.0, 15],
        'IPS 0.0 -> 15' => [10.0, 15],
    ]);

    it('ignores previous terms whose grades are not locked yet', function () {
        $previous = makeTerm($this->tenant, ['name' => '2025/2026', 'is_active' => false, 'starts_at' => today()->subYear()->toDateString(), 'ends_at' => today()->subMonths(6)->toDateString()]);
        $class = makeClass($this->tenant, ['term' => $previous, 'program' => $this->program]);
        giveGrade($class, $this->student, 90.0);

        expect($this->service->maxCredits($this->student, $this->term))->toBe(EnrollmentService::FIRST_SEMESTER_CREDITS);
    });
});

describe('prerequisites', function () {
    beforeEach(function () {
        $this->basic = Subject::create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program->id, 'name' => 'Dasar Pemrograman', 'code' => 'DP1', 'credits' => 3, 'semester' => 1]);
        $this->advanced = Subject::create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program->id, 'name' => 'Pemrograman Lanjut', 'code' => 'PL2', 'credits' => 3, 'semester' => 2]);
        $this->advanced->prerequisites()->attach($this->basic->id);
        $this->advancedClass = offer($this, 3, ['subject' => $this->advanced]);
        openEnrollmentWindow($this->term);
        $this->enrollment = $this->service->start($this->student, $this->term);
    });

    it('rejects a subject whose prerequisite is not passed', function () {
        expect(fn () => $this->service->saveSelection($this->enrollment, [$this->advancedClass->id]))
            ->toThrow(ValidationException::class, 'Dasar Pemrograman');
    });

    it('rejects a prerequisite that was failed with E', function () {
        passPreviousSubject($this, $this->basic, 10.0);

        expect(fn () => $this->service->saveSelection($this->enrollment, [$this->advancedClass->id]))
            ->toThrow(ValidationException::class, 'prasyarat');
    });

    it('accepts the subject once the prerequisite is passed', function () {
        passPreviousSubject($this, $this->basic, 75.0);

        $this->service->saveSelection($this->enrollment, [$this->advancedClass->id]);

        expect($this->enrollment->assignments()->count())->toBe(1);
    });
});

describe('payment requirement', function () {
    beforeEach(function () {
        openEnrollmentWindow($this->term);
        $this->class = offer($this);
        $this->enrollment = $this->service->start($this->student, $this->term);
        $this->invoice = Invoice::factory()->create(['tenant_id' => $this->tenant->id, 'student_id' => $this->student->id, 'academic_term_id' => $this->term->id, 'total' => 1000000]);
    });

    it('blocks the KRS while the semester invoice is unpaid', function () {
        expect(fn () => $this->service->saveSelection($this->enrollment, [$this->class->id]))
            ->toThrow(ValidationException::class, 'setelah tagihan semester ini lunas');
    });

    it('allows the KRS when the invoice is paid, exempted, or the rule is off', function (string $how) {
        match ($how) {
            'paid' => tap($this->invoice)->update(['status' => 'paid']),
            'exempt' => $this->invoice->update(['krs_exempt' => true]),
            'off' => $this->tenant->update(['settings' => ['krs_requires_payment' => false]]),
        };

        $this->service->saveSelection($this->enrollment->refresh(), [$this->class->id]);

        expect($this->enrollment->assignments()->count())->toBe(1);
    })->with(['paid', 'exempt', 'off']);

    it('does not block a cancelled invoice or a semester without invoice', function () {
        $this->invoice->update(['status' => 'cancelled']);

        $this->service->saveSelection($this->enrollment->refresh(), [$this->class->id]);

        expect($this->enrollment->assignments()->count())->toBe(1);
    });

    it('keeps blocking a partially paid invoice', function () {
        $this->invoice->update(['status' => 'partial']);
        Payment::create(['tenant_id' => $this->tenant->id, 'invoice_id' => $this->invoice->id, 'student_id' => $this->student->id, 'amount' => 400000, 'method' => 'transfer', 'paid_on' => today(), 'status' => 'confirmed']);

        expect(fn () => $this->service->saveSelection($this->enrollment->refresh(), [$this->class->id]))->toThrow(ValidationException::class, 'Rp 600.000');
    });
});

describe('selection rules', function () {
    beforeEach(function () {
        openEnrollmentWindow($this->term);
        $this->enrollment = $this->service->start($this->student, $this->term);
    });

    it('rejects two classes of the same subject', function () {
        $subject = Subject::create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program->id, 'name' => 'Basis Data', 'code' => 'BD1', 'credits' => 3, 'semester' => 1]);
        $a = offer($this, 3, ['subject' => $subject, 'section' => 'A']);
        $b = offer($this, 3, ['subject' => $subject, 'section' => 'B']);

        expect(fn () => $this->service->saveSelection($this->enrollment, [$a->id, $b->id]))->toThrow(ValidationException::class, 'satu kelas');
    });

    it('rejects a class that is already full', function () {
        $class = offer($this, 3, ['capacity' => 1]);
        $other = makeStudentUser($this->tenant)->student;
        approvedEnrollment($other, $this->term, [$class]);

        expect(fn () => $this->service->saveSelection($this->enrollment, [$class->id]))->toThrow(ValidationException::class, 'sudah penuh');
    });

    it('detects overlapping schedules on the same day', function () {
        $a = offer($this);
        $b = offer($this);
        $slotA = TimeSlot::create(['tenant_id' => $this->tenant->id, 'day' => 1, 'period_number' => 1, 'starts_at' => '08:00', 'ends_at' => '10:00']);
        $slotB = TimeSlot::create(['tenant_id' => $this->tenant->id, 'day' => 1, 'period_number' => 2, 'starts_at' => '09:00', 'ends_at' => '11:00']);
        Schedule::create(['tenant_id' => $this->tenant->id, 'teaching_assignment_id' => $a->id, 'time_slot_id' => $slotA->id]);
        Schedule::create(['tenant_id' => $this->tenant->id, 'teaching_assignment_id' => $b->id, 'time_slot_id' => $slotB->id]);

        expect(fn () => $this->service->saveSelection($this->enrollment, [$a->id, $b->id]))->toThrow(ValidationException::class, 'bentrok');
    });

    it('only offers subjects of the student program', function () {
        $otherProgram = makeProgram($this->tenant, 'Hukum');
        $foreign = makeClass($this->tenant, ['term' => $this->term, 'program' => $otherProgram]);
        $own = offer($this);

        $offered = $this->service->offerings($this->student, $this->term)->pluck('id')->all();

        expect($offered)->toContain($own->id)->not->toContain($foreign->id);
    });

    it('does not offer subjects that were already passed', function () {
        $subject = Subject::create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program->id, 'name' => 'Sudah Lulus', 'code' => 'SL1', 'credits' => 3, 'semester' => 1]);
        passPreviousSubject($this, $subject, 85.0);
        $again = offer($this, 3, ['subject' => $subject]);

        expect($this->service->offerings($this->student, $this->term)->pluck('id')->all())->not->toContain($again->id);
    });

    it('starts the KRS as a draft with the package of the student group', function () {
        $class = offer($this);
        joinGroup($class->studyGroup, $this->student);
        $enrollment = $this->service->start(makeStudentUser($this->tenant)->student, $this->term);
        $mine = Enrollment::query()->where('student_id', $this->student->id)->first();

        expect($enrollment->status)->toBe(EnrollmentStatus::Draft)->and($mine->status)->toBe(EnrollmentStatus::Draft);
    });
});

describe('submission and advisor decision', function () {
    beforeEach(function () {
        openEnrollmentWindow($this->term);
        $this->class = offer($this);
        $this->advisor = $this->class->teacher->user;
        $this->class->studyGroup->update(['homeroom_teacher_id' => $this->class->teacher_id]);
        joinGroup($this->class->studyGroup, $this->student);
        $this->enrollment = $this->service->start($this->student, $this->term);
        $this->actingAs($this->user);
    });

    it('needs at least one subject to submit', function () {
        $this->enrollment->assignments()->sync([]);

        expect(fn () => $this->service->submit($this->enrollment))->toThrow(ValidationException::class, 'minimal satu');
    });

    it('freezes the KRS after submission until it is withdrawn', function () {
        $this->service->submit($this->enrollment);

        expect($this->enrollment->refresh()->status)->toBe(EnrollmentStatus::Submitted)
            ->and(fn () => $this->service->saveSelection($this->enrollment, []))->toThrow(ValidationException::class, 'tidak bisa diubah');

        $this->service->withdraw($this->enrollment);
        expect($this->enrollment->refresh()->status)->toBe(EnrollmentStatus::Draft);
    });

    it('lets only the homeroom lecturer, admin, principal, or curriculum decide', function () {
        $this->service->submit($this->enrollment);
        $enrollment = $this->enrollment->refresh();

        $stranger = makeTeacherUser($this->tenant);

        expect($this->service->canDecide($this->advisor, $enrollment))->toBeTrue()
            ->and($this->service->canDecide($stranger, $enrollment))->toBeFalse()
            ->and($this->service->canDecide($this->user, $enrollment))->toBeFalse()
            ->and($this->service->canDecide(makeStaffUser($this->tenant, Role::Admin), $enrollment))->toBeTrue()
            ->and($this->service->canDecide(makeStaffUser($this->tenant, Role::Principal), $enrollment))->toBeTrue()
            ->and($this->service->canDecide(makeStaffUser($this->tenant, Role::Curriculum), $enrollment))->toBeTrue()
            ->and($this->service->canDecide(makeStaffUser($this->tenant, Role::Duty), $enrollment))->toBeFalse()
            ->and(fn () => $this->service->decide($enrollment, $stranger, true))->toThrow(HttpException::class);
    });

    it('requires a reason to reject and records the decision', function () {
        $this->service->submit($this->enrollment);
        $enrollment = $this->enrollment->refresh();

        expect(fn () => $this->service->decide($enrollment, $this->advisor, false))->toThrow(ValidationException::class, 'Alasan penolakan');

        $this->service->decide($enrollment, $this->advisor, false, 'SKS terlalu banyak');
        expect($enrollment->refresh()->status)->toBe(EnrollmentStatus::Rejected)->and($enrollment->advisor_note)->toBe('SKS terlalu banyak');
    });

    it('cannot decide a KRS that is not submitted', function () {
        expect(fn () => $this->service->decide($this->enrollment, $this->advisor, true))->toThrow(ValidationException::class, 'tidak sedang menunggu');
    });

    it('adds an approved student to the roster of a class of another group', function () {
        $foreign = offer($this);
        $enrollment = approvedEnrollment($this->student, $this->term, [$foreign]);

        expect(app(ClassRoster::class)->studentIds($foreign)->all())->toContain($this->student->id)
            ->and($enrollment->status)->toBe(EnrollmentStatus::Approved);
    });

    it('does not add a student whose KRS is only submitted', function () {
        $foreign = offer($this);
        $enrollment = approvedEnrollment($this->student, $this->term, [$foreign]);
        $enrollment->update(['status' => EnrollmentStatus::Submitted]);

        expect(app(ClassRoster::class)->studentIds($foreign)->all())->not->toContain($this->student->id);
    });

    it('moves a member to another section of the same subject through the approved KRS', function () {
        $subject = Subject::create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program->id, 'name' => 'Seksi', 'code' => 'SK1', 'credits' => 3, 'semester' => 1]);
        $own = offer($this, 3, ['subject' => $subject, 'group' => $this->class->studyGroup, 'section' => 'A']);
        $other = offer($this, 3, ['subject' => $subject, 'section' => 'B']);

        expect(app(ClassRoster::class)->studentIds($own)->all())->toContain($this->student->id);

        approvedEnrollment($this->student, $this->term, [$other]);

        expect(app(ClassRoster::class)->studentIds($own)->all())->not->toContain($this->student->id)
            ->and(app(ClassRoster::class)->studentIds($other)->all())->toContain($this->student->id);
    });

    it('can limit the roster to active students', function () {
        $foreign = offer($this);
        approvedEnrollment($this->student, $this->term, [$foreign]);
        $this->student->update(['status' => StudentStatus::Leave]);

        expect(app(ClassRoster::class)->studentIds($foreign)->all())->toContain($this->student->id)
            ->and(app(ClassRoster::class)->studentIds($foreign, activeOnly: true)->all())->not->toContain($this->student->id);
    });

    it('ignores group members who left before the term', function () {
        $former = makeStudentUser($this->tenant)->student;
        StudyGroupMember::create([
            'study_group_id' => $this->class->study_group_id, 'student_id' => $former->id,
            'joined_at' => today()->subYear(), 'left_at' => today()->subMonths(3),
        ]);

        expect(app(ClassRoster::class)->studentIds($this->class)->all())->not->toContain($former->id);
    });
});

it('keeps the student model usable for the tenant helper', function () {
    expect(Student::query()->where('tenant_id', $this->tenant->id)->count())->toBe(1);
});
