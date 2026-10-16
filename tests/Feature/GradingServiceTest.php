<?php

use App\Enums\GradeKind;
use App\Enums\GradeRevisionStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use App\Services\AcademicRecord;
use App\Services\GradingService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->tenant = makeTenant();
    $this->term = makeTerm($this->tenant, ['grading_closes_on' => today()->addMonth()->toDateString()]);
    $this->studentUsers = collect(range(1, 3))->map(fn () => makeStudentUser($this->tenant));
    $this->students = $this->studentUsers->map->student;
    $this->class = makeClass($this->tenant, ['term' => $this->term, 'students' => $this->students->all()]);
    $this->teacher = $this->class->teacher->user;
    $this->service = app(GradingService::class);
    $this->actingAs($this->teacher);
});

/**
 * @return array<int, array{assignment: int, midterm: int, final: int}>
 */
function fullScores(object $test, int $assignment = 80, int $midterm = 80, int $final = 80): array
{
    return $test->students->mapWithKeys(fn (Student $student) => [$student->id => ['assignment' => $assignment, 'midterm' => $midterm, 'final' => $final]])->all();
}

describe('input guard', function () {
    it('lets only the lecturer of the class input grades', function () {
        $other = makeTeacherUser($this->tenant);
        $admin = makeStaffUser($this->tenant, Role::Admin);

        expect($this->service->inputBlockReason($this->class, $this->teacher))->toBeNull()
            ->and($this->service->inputBlockReason($this->class, $other))->toContain('dosen pengampu')
            ->and($this->service->inputBlockReason($this->class, $admin))->toContain('dosen pengampu')
            ->and(fn () => $this->service->saveScores($this->class, $other, fullScores($this)))->toThrow(ValidationException::class);
    });

    it('blocks input once the grades are locked or the term is locked', function () {
        $this->term->update(['is_locked' => true]);
        expect($this->service->inputBlockReason($this->class->refresh(), $this->teacher))->toContain('Semester ini sudah dikunci');

        $this->term->update(['is_locked' => false]);
        lockGrades($this->class);
        expect($this->service->inputBlockReason($this->class->refresh(), $this->teacher))->toContain('Nilai sudah dikunci');
    });

    it('blocks input after the deadline unless the class was extended', function () {
        $this->term->update(['grading_closes_on' => today()->subDay()->toDateString()]);
        $class = $this->class->refresh();

        expect($this->service->isPastDeadline($class))->toBeTrue()
            ->and($this->service->inputBlockReason($class, $this->teacher))->toContain('Batas waktu input nilai sudah lewat');

        $class->update(['grading_extended_until' => today()->addDays(3)->toDateString()]);

        expect($this->service->deadlineFor($class->refresh())->toDateString())->toBe(today()->addDays(3)->toDateString())
            ->and($this->service->inputBlockReason($class, $this->teacher))->toBeNull();
    });

    it('allows input on the deadline day itself and when no deadline is set', function () {
        $this->term->update(['grading_closes_on' => today()->toDateString()]);
        expect($this->service->isPastDeadline($this->class->refresh()))->toBeFalse();

        $this->term->update(['grading_closes_on' => null]);
        expect($this->service->deadlineFor($this->class->refresh()))->toBeNull()->and($this->service->isPastDeadline($this->class))->toBeFalse();
    });
});

describe('weights', function () {
    it('requires the four weights to be whole numbers summing to 100', function () {
        $ok = ['assignment' => 20, 'midterm' => 30, 'final' => 40, 'attendance' => 10];

        $this->service->saveWeights($this->class, $this->teacher, $ok);
        expect($this->class->refresh()->weight_final)->toBe(40);

        foreach ([
            ['assignment' => 20, 'midterm' => 30, 'final' => 40, 'attendance' => 20],
            ['assignment' => 20, 'midterm' => 30, 'final' => 40, 'attendance' => 'x'],
            ['assignment' => -10, 'midterm' => 50, 'final' => 50, 'attendance' => 10],
            ['assignment' => 20.5, 'midterm' => 29.5, 'final' => 40, 'attendance' => 10],
            ['assignment' => 20, 'midterm' => 30, 'final' => 40],
        ] as $bad) {
            expect(fn () => $this->service->saveWeights($this->class, $this->teacher, $bad))->toThrow(ValidationException::class);
        }

        expect($this->class->refresh()->weight_final)->toBe(40);
    });

    it('recalculates existing totals when the weights change', function () {
        $this->service->saveScores($this->class, $this->teacher, fullScores($this, 100, 50, 80));
        $grade = Grade::where('student_id', $this->students[0]->id)->firstOrFail();
        expect((float) $grade->total_score)->toBe(0.3 * 100 + 0.25 * 50 + 0.35 * 80 + 0.0);

        $this->service->saveWeights($this->class, $this->teacher, ['assignment' => 100, 'midterm' => 0, 'final' => 0, 'attendance' => 0]);

        expect((float) $grade->refresh()->total_score)->toBe(100.0)->and($grade->letter)->toBe('A');
    });

    it('cannot change weights after locking', function () {
        $this->service->saveScores($this->class, $this->teacher, fullScores($this));
        $this->service->lock($this->class, $this->teacher);

        expect(fn () => $this->service->saveWeights($this->class->refresh(), $this->teacher, ['assignment' => 25, 'midterm' => 25, 'final' => 25, 'attendance' => 25]))
            ->toThrow(ValidationException::class, 'dikunci');
    });
});

describe('scores', function () {
    it('computes the weighted total, letter, and points with the attendance component', function () {
        recordAttendance($this->class, $this->students[0], ['hadir']);

        $this->service->saveScores($this->class, $this->teacher, [$this->students[0]->id => ['assignment' => 90, 'midterm' => 70, 'final' => 80]]);

        $grade = Grade::where('student_id', $this->students[0]->id)->firstOrFail();

        expect((float) $grade->attendance_score)->toBe(100.0)
            ->and((float) $grade->total_score)->toBe(82.5)
            ->and($grade->letter)->toBe('A')
            ->and((float) $grade->points)->toBe(4.0);
    });

    it('counts izin, sakit, and alpa as not attended', function () {
        recordAttendance($this->class, $this->students[0], ['hadir', 'izin', 'sakit', 'alpa']);

        expect($this->service->attendanceScores($this->class, [$this->students[0]->id]))->toBe([$this->students[0]->id => 25.0]);
    });

    it('leaves the total empty until all three components are present', function () {
        $this->service->saveScores($this->class, $this->teacher, [$this->students[0]->id => ['assignment' => 90, 'midterm' => '', 'final' => 80]]);

        $grade = Grade::where('student_id', $this->students[0]->id)->firstOrFail();

        expect($grade->total_score)->toBeNull()->and($grade->letter)->toBeNull();
    });

    it('rejects scores outside 0-100 and non-numbers without saving anything', function () {
        foreach ([101, -1, 'abc'] as $bad) {
            expect(fn () => $this->service->saveScores($this->class, $this->teacher, [
                $this->students[0]->id => ['assignment' => 80, 'midterm' => 80, 'final' => 80],
                $this->students[1]->id => ['assignment' => $bad, 'midterm' => 80, 'final' => 80],
            ]))->toThrow(ValidationException::class);
        }

        expect(Grade::count())->toBe(0);
    });

    it('ignores students outside the class roster', function () {
        $outsider = makeStudentUser($this->tenant)->student;

        $saved = $this->service->saveScores($this->class, $this->teacher, fullScores($this) + [$outsider->id => ['assignment' => 100, 'midterm' => 100, 'final' => 100]]);

        expect($saved)->toBe(3)->and(Grade::where('student_id', $outsider->id)->exists())->toBeFalse();
    });

    it('maps the total to letters using the campus scale boundaries', function (float $score, string $letter, float $points) {
        $this->service->saveWeights($this->class, $this->teacher, ['assignment' => 100, 'midterm' => 0, 'final' => 0, 'attendance' => 0]);
        $this->service->saveScores($this->class, $this->teacher, [$this->students[0]->id => ['assignment' => $score, 'midterm' => 0, 'final' => 0]]);

        $grade = Grade::where('student_id', $this->students[0]->id)->firstOrFail();

        expect($grade->letter)->toBe($letter)->and((float) $grade->points)->toBe($points);
    })->with([[80.0, 'A', 4.0], [79.99, 'B', 3.0], [70.0, 'B', 3.0], [60.0, 'C', 2.0], [50.0, 'D', 1.0], [49.99, 'E', 0.0]]);

    it('follows the weights and scale configured by the campus', function () {
        $this->tenant->update(['settings' => ['grade_weights' => ['assignment' => 100, 'midterm' => 0, 'final' => 0, 'attendance' => 0], 'grade_scale' => ['A' => 90, 'B' => 80, 'C' => 70, 'D' => 60]]]);
        $this->actingAs($this->teacher->refresh());

        $this->service->saveScores($this->class, $this->teacher->refresh(), [$this->students[0]->id => ['assignment' => 85, 'midterm' => 0, 'final' => 0]]);

        expect(Grade::where('student_id', $this->students[0]->id)->value('letter'))->toBe('B');
    });
});

describe('locking', function () {
    it('refuses to lock an empty class or incomplete grades', function () {
        $empty = makeClass($this->tenant, ['term' => $this->term, 'teacher' => $this->teacher]);

        expect(fn () => $this->service->lock($empty, $this->teacher))->toThrow(ValidationException::class, 'Belum ada mahasiswa');

        $this->service->saveScores($this->class, $this->teacher, [
            $this->students[0]->id => ['assignment' => 80, 'midterm' => 80, 'final' => 80],
            $this->students[1]->id => ['assignment' => 80, 'midterm' => 80, 'final' => ''],
        ]);

        expect(fn () => $this->service->lock($this->class, $this->teacher))->toThrow(ValidationException::class, 'belum lengkap');
        expect($this->class->refresh()->areGradesLocked())->toBeFalse();
    });

    it('locks, refreshes attendance, and writes an audit entry', function () {
        $this->service->saveScores($this->class, $this->teacher, fullScores($this));
        recordAttendance($this->class, $this->students[0], ['hadir', 'hadir']);

        $this->service->lock($this->class, $this->teacher);

        $grade = Grade::where('student_id', $this->students[0]->id)->firstOrFail();

        expect($this->class->refresh()->areGradesLocked())->toBeTrue()
            ->and($this->class->grades_locked_by)->toBe($this->teacher->id)
            ->and((float) $grade->attendance_score)->toBe(100.0)
            ->and((float) $grade->total_score)->toBe(82.0)
            ->and(AuditLog::where('action', 'grades.locked')->count())->toBe(1);
    });

    it('cannot be locked or edited again by the lecturer afterwards', function () {
        $this->service->saveScores($this->class, $this->teacher, fullScores($this));
        $this->service->lock($this->class, $this->teacher);

        expect(fn () => $this->service->saveScores($this->class->refresh(), $this->teacher, fullScores($this, 10, 10, 10)))->toThrow(ValidationException::class, 'dikunci')
            ->and(fn () => $this->service->lock($this->class->refresh(), $this->teacher))->toThrow(ValidationException::class, 'dikunci');
    });

    it('lets the lecturer add a repeating student who joins through another class', function () {
        $repeater = makeStudentUser($this->tenant)->student;

        $this->service->addRepeater($this->class, $this->teacher, $repeater->nim);

        expect($this->service->roster($this->class)->pluck('id')->all())->toContain($repeater->id)
            ->and(fn () => $this->service->addRepeater($this->class, $this->teacher, $repeater->nim))->toThrow(ValidationException::class, 'sudah ada')
            ->and(fn () => $this->service->addRepeater($this->class, $this->teacher, 'TIDAKADA'))->toThrow(ValidationException::class, 'tidak ditemukan');
    });
});

describe('revisions', function () {
    beforeEach(function () {
        $this->service->saveScores($this->class, $this->teacher, fullScores($this, 60, 60, 60));
        $this->service->lock($this->class, $this->teacher);
        $this->grade = Grade::where('student_id', $this->students[0]->id)->firstOrFail();
        $this->principal = makeStaffUser($this->tenant, Role::Principal);
        $this->new = ['assignment' => 100, 'midterm' => 100, 'final' => 100];
    });

    it('requires a locked class, the lecturer, a real reason, and an actual change', function () {
        $open = makeClass($this->tenant, ['term' => $this->term, 'teacher' => $this->teacher, 'students' => [$this->students[0]->refresh()]]);
        $this->service->saveScores($open, $this->teacher, [$this->students[0]->id => ['assignment' => 50, 'midterm' => 50, 'final' => 50]]);
        $openGrade = Grade::where('teaching_assignment_id', $open->id)->firstOrFail();

        expect(fn () => $this->service->requestRevision($openGrade, $this->teacher, $this->new, 'Alasan yang cukup panjang'))->toThrow(ValidationException::class, 'sudah dikunci')
            ->and(fn () => $this->service->requestRevision($this->grade, makeTeacherUser($this->tenant), $this->new, 'Alasan yang cukup panjang'))->toThrow(ValidationException::class, 'dosen pengampu')
            ->and(fn () => $this->service->requestRevision($this->grade, $this->teacher, $this->new, 'pendek'))->toThrow(ValidationException::class, 'minimal 10')
            ->and(fn () => $this->service->requestRevision($this->grade, $this->teacher, ['assignment' => 60, 'midterm' => 60, 'final' => 60], 'Alasan yang cukup panjang'))->toThrow(ValidationException::class, 'Tidak ada perubahan')
            ->and(fn () => $this->service->requestRevision($this->grade, $this->teacher, ['assignment' => 101, 'midterm' => 60, 'final' => 60], 'Alasan yang cukup panjang'))->toThrow(ValidationException::class);
    });

    it('keeps the grade untouched while the revision waits and allows only one pending request', function () {
        $revision = $this->service->requestRevision($this->grade, $this->teacher, $this->new, 'Salah input nilai UAS');

        expect($revision->status)->toBe(GradeRevisionStatus::Pending)
            ->and((float) $this->grade->refresh()->total_score)->toBe(60.0 * 0.9)
            ->and($revision->new_scores['letter'])->toBe('A')
            ->and(fn () => $this->service->requestRevision($this->grade, $this->teacher, ['assignment' => 90, 'midterm' => 90, 'final' => 90], 'Salah input nilai UAS lagi'))->toThrow(ValidationException::class, 'menunggu');
    });

    it('is decided only by the principal, not by an admin or the lecturer', function () {
        $revision = $this->service->requestRevision($this->grade, $this->teacher, $this->new, 'Salah input nilai UAS');

        foreach ([makeStaffUser($this->tenant, Role::Admin), $this->teacher, makeStaffUser($this->tenant, Role::Curriculum)] as $decider) {
            expect(fn () => $this->service->decideRevision($revision, $decider, true))->toThrow(ValidationException::class, 'Hanya Kaprodi');
        }

        expect($revision->refresh()->status)->toBe(GradeRevisionStatus::Pending);
    });

    it('applies the new scores and letter on approval and audits it', function () {
        $revision = $this->service->requestRevision($this->grade, $this->teacher, $this->new, 'Salah input nilai UAS');

        $this->service->decideRevision($revision, $this->principal, true);

        expect($revision->refresh()->status)->toBe(GradeRevisionStatus::Approved)
            ->and($revision->decided_by)->toBe($this->principal->id)
            ->and((float) $this->grade->refresh()->final_exam_score)->toBe(100.0)
            ->and($this->grade->letter)->toBe('A')
            ->and(AuditLog::where('action', 'grades.revision_decided')->count())->toBe(1)
            ->and(fn () => $this->service->decideRevision($revision, $this->principal, true))->toThrow(ValidationException::class, 'sudah diputuskan');
    });

    it('requires a note to reject and keeps the original grade', function () {
        $revision = $this->service->requestRevision($this->grade, $this->teacher, $this->new, 'Salah input nilai UAS');

        expect(fn () => $this->service->decideRevision($revision, $this->principal, false))->toThrow(ValidationException::class, 'Alasan penolakan');

        $this->service->decideRevision($revision, $this->principal, false, 'Tidak ada bukti');

        expect($revision->refresh()->status)->toBe(GradeRevisionStatus::Rejected)->and((float) $this->grade->refresh()->final_exam_score)->toBe(60.0);
    });
});

describe('remedial and best grade', function () {
    beforeEach(function () {
        $this->service->saveScores($this->class, $this->teacher, [
            $this->students[0]->id => ['assignment' => 40, 'midterm' => 40, 'final' => 40],
            $this->students[1]->id => ['assignment' => 90, 'midterm' => 90, 'final' => 90],
            $this->students[2]->id => ['assignment' => 40, 'midterm' => 40, 'final' => 40],
        ]);
    });

    it('is only for locked classes, the lecturer, and remedial letters', function () {
        expect(fn () => $this->service->saveRemedial($this->class, $this->teacher, $this->students[0]->id, 80))->toThrow(ValidationException::class, 'setelah nilai reguler dikunci');

        $this->service->lock($this->class, $this->teacher);
        $class = $this->class->refresh();

        expect(fn () => $this->service->saveRemedial($class, makeTeacherUser($this->tenant), $this->students[0]->id, 80))->toThrow(ValidationException::class, 'dosen pengampu')
            ->and(fn () => $this->service->saveRemedial($class, $this->teacher, $this->students[1]->id, 80))->toThrow(ValidationException::class, 'tidak berhak')
            ->and(fn () => $this->service->saveRemedial($class, $this->teacher, $this->students[0]->id, 150))->toThrow(ValidationException::class, '0')
            ->and(fn () => $this->service->saveRemedial($class, $this->teacher, $this->students[0]->id, 'x'))->toThrow(ValidationException::class);

        $this->term->update(['is_locked' => true]);
        expect(fn () => $this->service->saveRemedial($class->refresh(), $this->teacher, $this->students[0]->id, 80))->toThrow(ValidationException::class, 'Semester ini sudah dikunci');
    });

    it('stores the remedial attempt and the best attempt wins in the transcript', function () {
        $this->service->lock($this->class, $this->teacher);

        $remedial = $this->service->saveRemedial($this->class->refresh(), $this->teacher, $this->students[0]->id, 85);
        $records = app(AcademicRecord::class);
        $best = $records->best($records->attempts($this->students[0]));

        expect($remedial->kind)->toBe(GradeKind::Remedial)->and($remedial->letter)->toBe('A')
            ->and($best->id)->toBe($remedial->id)
            ->and($records->transcript($this->students[0])['ipk'])->toBe(4.0);

        $worse = $this->service->saveRemedial($this->class, $this->teacher, $this->students[2]->id, 20);
        $records2 = app(AcademicRecord::class)->best(app(AcademicRecord::class)->attempts($this->students[2]));

        expect($worse->letter)->toBe('E')->and($records2->kind)->toBe(GradeKind::Regular);
    });

    it('keeps one remedial row per student and updates it', function () {
        $this->service->lock($this->class, $this->teacher);

        $this->service->saveRemedial($this->class->refresh(), $this->teacher, $this->students[0]->id, 55);
        $this->service->saveRemedial($this->class, $this->teacher, $this->students[0]->id, 65);

        expect(Grade::where('student_id', $this->students[0]->id)->where('kind', GradeKind::Remedial)->count())->toBe(1);
    });
});

describe('academic record', function () {
    it('counts only locked grades in the KHS, IPS, and IPK', function () {
        $student = $this->students[0];
        $locked = makeClass($this->tenant, ['term' => $this->term, 'credits' => 3]);
        $open = makeClass($this->tenant, ['term' => $this->term, 'credits' => 3]);
        giveGrade($locked, $student, 85);
        lockGrades($locked);
        giveGrade($open, $student, 40);

        $records = app(AcademicRecord::class);
        $khs = $records->khs($student, $this->term);
        $transcript = $records->transcript($student);

        expect($khs['rows'])->toHaveCount(1)
            ->and($khs['credits'])->toBe(3)
            ->and($khs['ips'])->toBe(4.0)
            ->and($khs['pending'])->toHaveCount(1)
            ->and($transcript['ipk'])->toBe(4.0)
            ->and($transcript['credits_passed'])->toBe(3);
    });

    it('weights the IPK by credits', function () {
        $student = $this->students[0];
        $a = makeClass($this->tenant, ['term' => $this->term, 'credits' => 4]);
        $c = makeClass($this->tenant, ['term' => $this->term, 'credits' => 2]);
        giveGrade($a, $student, 85);
        giveGrade($c, $student, 65);
        lockGrades($a);
        lockGrades($c);

        expect(app(AcademicRecord::class)->transcript($student)['ipk'])->toBe(round((4 * 4 + 2 * 2) / 6, 2));
    });

    it('includes legacy credits and quality points in the IPK and passed credits', function () {
        $student = $this->students[0];
        $student->update(['legacy_credits' => 60, 'legacy_quality_points' => 150.0]);
        $class = makeClass($this->tenant, ['term' => $this->term, 'credits' => 3]);
        giveGrade($class, $student, 85);
        lockGrades($class);

        $transcript = app(AcademicRecord::class)->transcript($student->refresh());

        expect($transcript['credits_passed'])->toBe(63)->and($transcript['ipk'])->toBe(round((150 + 12) / 63, 2));
    });

    it('uses the best of repeated attempts across terms and does not count an E as passed', function () {
        $student = $this->students[0];
        $subject = Subject::create(['tenant_id' => $this->tenant->id, 'program_id' => makeProgram($this->tenant)->id, 'name' => 'Kalkulus', 'code' => 'KAL', 'credits' => 3, 'semester' => 1]);
        $oldTerm = makeTerm($this->tenant, ['name' => '2025/2026', 'is_active' => false, 'starts_at' => today()->subYear()->toDateString(), 'ends_at' => today()->subMonths(6)->toDateString()]);
        $first = makeClass($this->tenant, ['term' => $oldTerm, 'subject' => $subject]);
        $second = makeClass($this->tenant, ['term' => $this->term, 'subject' => $subject]);
        giveGrade($first, $student, 10);
        lockGrades($first);

        $transcript = app(AcademicRecord::class)->transcript($student);
        expect($transcript['credits_passed'])->toBe(0)->and($transcript['rows'][0]['passed'])->toBeFalse();

        giveGrade($second, $student, 75);
        lockGrades($second);

        $transcript = app(AcademicRecord::class)->transcript($student);

        expect($transcript['rows'])->toHaveCount(1)
            ->and($transcript['rows'][0]['letter'])->toBe('B')
            ->and($transcript['rows'][0]['repeated'])->toBeTrue()
            ->and($transcript['credits_passed'])->toBe(3);
    });

    it('breaks a tie on points by the higher total score', function () {
        $student = $this->students[0];
        $a = makeClass($this->tenant, ['term' => $this->term]);
        $first = giveGrade($a, $student, 72);
        $second = giveGrade($a, $student, 78, GradeKind::Remedial);
        lockGrades($a);

        expect(app(AcademicRecord::class)->best(collect([$first, $second->load('assignment.academicTerm')])->map(fn ($g) => $g->load('assignment.academicTerm')))->id)->toBe($second->id);
    });

    it('does not reveal grades of a class that is not locked to the eligibility summary', function () {
        $class = makeClass($this->tenant, ['term' => $this->term]);
        giveGrade($class, $this->students[0], 90);

        expect(app(AcademicRecord::class)->eligibility($this->students[0]))->toBe(['credits' => 0, 'gpa' => null, 'has_grades' => false]);
    });
});
