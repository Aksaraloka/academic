<?php

use App\Enums\GradeKind;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Grade;
use App\Models\User;
use App\Services\GradingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->keyDirectory = sys_get_temp_dir().'/academic-grades-keys-'.getmypid();
    File::ensureDirectoryExists($this->keyDirectory);
    Passport::loadKeysFrom($this->keyDirectory);
    Artisan::call('passport:keys', ['--force' => true]);

    $this->tenant = makeTenant();
    config(['integration.lms.url' => 'http://aksaraloka.test', 'integration.lms.tenant_id' => $this->tenant->id]);

    $this->serverClient = app(ClientRepository::class)->createClientCredentialsGrantClient('Aksaraloka LMS (Server)');
    $this->ssoClient = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Aksaraloka LMS (SSO)', ['http://aksaraloka.test/auth/academic/callback']);

    $this->term = makeTerm($this->tenant, ['grading_closes_on' => today()->addMonth()->toDateString()]);
    $this->teacherUser = makeTeacherUser($this->tenant);
    $this->s1 = makeStudentUser($this->tenant);
    $this->s2 = makeStudentUser($this->tenant);
    $this->class = makeClass($this->tenant, ['term' => $this->term, 'teacher' => $this->teacherUser, 'students' => [$this->s1->student, $this->s2->student]]);
    $this->url = "/api/integration/v1/classes/{$this->class->id}/grades";
    $this->token = serverToken('grades.write');
});

afterEach(fn () => File::deleteDirectory($this->keyDirectory));

/**
 * @param  array<int, float|int>  $scores  user_id => nilai
 * @return array<string, mixed>
 */
function gradesPayload(object $test, array $scores, string $component = 'assignment', ?int $submittedBy = null): array
{
    return [
        'component' => $component,
        'submitted_by' => $submittedBy ?? $test->teacherUser->id,
        'scores' => collect($scores)->map(fn ($score, $userId) => ['user_id' => $userId, 'score' => $score])->values()->all(),
    ];
}

describe('authentication and scopes', function () {
    it('answers 401 without a token and 403 with the wrong scope', function () {
        $this->postJson($this->url, gradesPayload($this, [$this->s1->id => 80]))->assertUnauthorized();
        $this->withToken('rusak')->postJson($this->url, gradesPayload($this, [$this->s1->id => 80]))->assertUnauthorized();
        $this->withToken(serverToken('sync.read'))->postJson($this->url, gradesPayload($this, [$this->s1->id => 80]))->assertForbidden();

        expect(Grade::count())->toBe(0);
    });

    it('rejects a user (SSO) token even when it requests grades.write', function () {
        $token = userToken($this->teacherUser, 'grades.write');

        $this->withToken($token)->postJson($this->url, gradesPayload($this, [$this->s1->id => 80]))->assertUnauthorized();

        expect(Grade::count())->toBe(0);
    });

    it('does not let a read-only token write and a write-only token read', function () {
        $this->withToken(serverToken('grades.write'))->getJson('/api/integration/v1/terms')->assertForbidden();
    });
});

describe('saving a component', function () {
    it('stores the component for roster students and returns the counts', function () {
        $response = $this->withToken($this->token)->postJson($this->url, gradesPayload($this, [$this->s1->id => 80.5, $this->s2->id => 60]))
            ->assertOk();

        $grade = Grade::where('student_id', $this->s1->student->id)->firstOrFail();

        expect($response->json())->toBe(['updated' => 2, 'skipped' => []])
            ->and((float) $grade->assignment_score)->toBe(80.5)
            ->and($grade->kind)->toBe(GradeKind::Regular)
            ->and($grade->midterm_score)->toBeNull()
            ->and($grade->total_score)->toBeNull();
    });

    it('computes total, letter, and points through the same rules as the lecturer sheet once all components are present', function () {
        $token = $this->token;
        foreach (['assignment' => 90, 'midterm' => 70, 'final' => 80] as $component => $score) {
            $this->withToken($token)->postJson($this->url, gradesPayload($this, [$this->s1->id => $score], $component))->assertOk();
        }
        recordAttendance($this->class, $this->s1->student, ['hadir']);

        $this->withToken($token)->postJson($this->url, gradesPayload($this, [$this->s1->id => 90]))->assertOk();

        $grade = Grade::where('student_id', $this->s1->student->id)->firstOrFail();

        // 90*30 + 70*25 + 80*35 + 100*10 = 82.5 (bobot bawaan kampus, kehadiran dari presensi)
        expect((float) $grade->total_score)->toBe(82.5)->and($grade->letter)->toBe('A')->and((float) $grade->points)->toBe(4.0)->and((float) $grade->attendance_score)->toBe(100.0);
    });

    it('matches what the lecturer would get by saving the same scores in the sheet', function () {
        $viaSheet = makeClass($this->tenant, ['term' => $this->term, 'teacher' => $this->teacherUser, 'students' => [$this->s1->student]]);
        app(GradingService::class)->saveScores($viaSheet, $this->teacherUser, [$this->s1->student->id => ['assignment' => 77, 'midterm' => 66, 'final' => 55]]);

        foreach (['assignment' => 77, 'midterm' => 66, 'final' => 55] as $component => $score) {
            $this->withToken($this->token)->postJson($this->url, gradesPayload($this, [$this->s1->id => $score], $component))->assertOk();
        }

        $sheet = Grade::where('teaching_assignment_id', $viaSheet->id)->firstOrFail();
        $lms = Grade::where('teaching_assignment_id', $this->class->id)->where('student_id', $this->s1->student->id)->firstOrFail();

        expect($lms->only(['total_score', 'letter', 'points']))->toBe($sheet->only(['total_score', 'letter', 'points']));
    });

    it('never overwrites the other components with empty values', function () {
        $this->withToken($this->token)->postJson($this->url, gradesPayload($this, [$this->s1->id => 90], 'assignment'))->assertOk();
        $this->withToken($this->token)->postJson($this->url, gradesPayload($this, [$this->s1->id => 70], 'midterm'))->assertOk();
        $this->withToken($this->token)->postJson($this->url, gradesPayload($this, [$this->s2->id => 50], 'final'))->assertOk();

        $one = Grade::where('student_id', $this->s1->student->id)->firstOrFail();
        $two = Grade::where('student_id', $this->s2->student->id)->firstOrFail();

        expect((float) $one->assignment_score)->toBe(90.0)->and((float) $one->midterm_score)->toBe(70.0)->and($one->final_exam_score)->toBeNull()
            ->and($two->assignment_score)->toBeNull()->and((float) $two->final_exam_score)->toBe(50.0);
    });

    it('overwrites the same component on a repeated submission and keeps one row per student', function () {
        $this->withToken($this->token)->postJson($this->url, gradesPayload($this, [$this->s1->id => 40]))->assertOk();
        $this->withToken($this->token)->postJson($this->url, gradesPayload($this, [$this->s1->id => 95]))->assertOk();

        expect(Grade::where('student_id', $this->s1->student->id)->count())->toBe(1)
            ->and((float) Grade::where('student_id', $this->s1->student->id)->value('assignment_score'))->toBe(95.0);
    });

    it('accepts the boundaries 0 and 100', function () {
        $this->withToken($this->token)->postJson($this->url, gradesPayload($this, [$this->s1->id => 0, $this->s2->id => 100]))->assertOk()->assertJson(['updated' => 2]);
    });

    it('skips users who are not in the roster instead of failing', function () {
        $outsider = makeStudentUser($this->tenant);
        $otherTenantStudent = makeStudentUser(makeTenant());

        $response = $this->withToken($this->token)->postJson($this->url, gradesPayload($this, [
            $this->s1->id => 80, $outsider->id => 90, $otherTenantStudent->id => 70, 999999 => 60,
        ]))->assertOk();

        expect($response->json('updated'))->toBe(1)
            ->and(collect($response->json('skipped'))->pluck('user_id')->sort()->values()->all())->toBe(collect([$outsider->id, $otherTenantStudent->id, 999999])->sort()->values()->all())
            ->and(Grade::count())->toBe(1);
    });

    it('accepts approved-KRS visitors as part of the roster', function () {
        $visitor = makeStudentUser($this->tenant);
        approvedEnrollment($visitor->student, $this->term, [$this->class]);

        $this->withToken($this->token)->postJson($this->url, gradesPayload($this, [$visitor->id => 85]))->assertOk()->assertJson(['updated' => 1, 'skipped' => []]);
    });

    it('does not touch remedial rows or other classes', function () {
        $other = makeClass($this->tenant, ['term' => $this->term, 'teacher' => $this->teacherUser, 'students' => [$this->s1->student]]);
        giveGrade($other, $this->s1->student, 30);

        $this->withToken($this->token)->postJson($this->url, gradesPayload($this, [$this->s1->id => 80]))->assertOk();

        expect((float) Grade::where('teaching_assignment_id', $other->id)->value('total_score'))->toBe(30.0);
    });
});

describe('authorization of the submitter', function () {
    it('answers 403 when submitted_by is not the lecturer of the class', function () {
        $stranger = makeTeacherUser($this->tenant);

        foreach ([$stranger->id, $this->s1->id, makeStaffUser($this->tenant, Role::Principal)->id, makeStaffUser($this->tenant, Role::Curriculum)->id, 999999] as $submitter) {
            $this->withToken($this->token)->postJson($this->url, gradesPayload($this, [$this->s1->id => 80], 'assignment', $submitter))->assertForbidden();
        }

        expect(Grade::count())->toBe(0);
    });

    it('lets an admin of the tenant submit, but not an admin of another tenant', function () {
        $admin = makeStaffUser($this->tenant, Role::Admin);
        $foreignAdmin = makeStaffUser(makeTenant(), Role::Admin);

        $this->withToken($this->token)->postJson($this->url, gradesPayload($this, [$this->s1->id => 80], 'assignment', $foreignAdmin->id))->assertForbidden();
        $this->withToken($this->token)->postJson($this->url, gradesPayload($this, [$this->s1->id => 80], 'assignment', $admin->id))->assertOk();

        expect(AuditLog::where('action', 'grades.integration_saved')->value('user_id'))->toBe($admin->id);
    });

    it('rejects a teacher account of another tenant even if the ids match nothing', function () {
        $foreignTeacher = makeTeacherUser(makeTenant());

        $this->withToken($this->token)->postJson($this->url, gradesPayload($this, [$this->s1->id => 80], 'assignment', $foreignTeacher->id))->assertForbidden();
    });
});

describe('blocked states', function () {
    it('answers 409 with the reason when the grades are locked', function () {
        lockGrades($this->class);

        $response = $this->withToken($this->token)->postJson($this->url, gradesPayload($this, [$this->s1->id => 80]))->assertStatus(409);

        expect($response->json('message'))->toContain('Nilai sudah dikunci')->and(Grade::count())->toBe(0);
    });

    it('answers 409 when the term is locked or the deadline has passed, unless extended', function () {
        $this->term->update(['is_locked' => true]);
        $this->withToken($this->token)->postJson($this->url, gradesPayload($this, [$this->s1->id => 80]))->assertStatus(409)->assertJsonPath('message', 'Semester ini sudah dikunci.');

        $this->term->update(['is_locked' => false, 'grading_closes_on' => today()->subDay()->toDateString()]);
        $this->withToken($this->token)->postJson($this->url, gradesPayload($this, [$this->s1->id => 80]))->assertStatus(409)->assertJsonPath('message', fn ($m) => str_contains($m, 'Batas waktu input nilai'));

        $this->class->update(['grading_extended_until' => today()->addDays(2)->toDateString()]);
        $this->withToken($this->token)->postJson($this->url, gradesPayload($this, [$this->s1->id => 80]))->assertOk();
    });

    it('checks authorization before reporting that the class is locked', function () {
        lockGrades($this->class);

        $this->withToken($this->token)->postJson($this->url, gradesPayload($this, [$this->s1->id => 80], 'assignment', $this->s1->id))->assertForbidden();
    });

    it('stops a lecturer submission from changing locked grades even one by one', function () {
        $this->withToken($this->token)->postJson($this->url, gradesPayload($this, [$this->s1->id => 50]))->assertOk();
        lockGrades($this->class);

        $this->withToken($this->token)->postJson($this->url, gradesPayload($this, [$this->s1->id => 100]))->assertStatus(409);

        expect((float) Grade::where('student_id', $this->s1->student->id)->value('assignment_score'))->toBe(50.0);
    });
});

describe('validation', function () {
    it('answers 422 for scores outside 0-100 or not numeric and saves nothing', function (mixed $bad) {
        $this->withToken($this->token)->postJson($this->url, gradesPayload($this, [$this->s1->id => 80, $this->s2->id => $bad]))
            ->assertUnprocessable()->assertJsonValidationErrors('scores.1.score');

        expect(Grade::count())->toBe(0);
    })->with([100.01, 101, -1, 'abc', null]);

    it('answers 422 for an unknown component and for attendance', function (string $component) {
        $this->withToken($this->token)->postJson($this->url, gradesPayload($this, [$this->s1->id => 80], $component))
            ->assertUnprocessable()->assertJsonValidationErrors('component');
    })->with(['attendance', 'total', 'bonus', '']);

    it('answers 422 for a malformed body', function (array $payload, string $field) {
        $this->withToken($this->token)->postJson($this->url, $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
    })->with([
        'no scores' => [['component' => 'assignment', 'submitted_by' => 1], 'scores'],
        'empty scores' => [['component' => 'assignment', 'submitted_by' => 1, 'scores' => []], 'scores'],
        'no submitter' => [['component' => 'assignment', 'scores' => [['user_id' => 1, 'score' => 5]]], 'submitted_by'],
        'row without user' => [['component' => 'assignment', 'submitted_by' => 1, 'scores' => [['score' => 5]]], 'scores.0.user_id'],
        'row without score' => [['component' => 'assignment', 'submitted_by' => 1, 'scores' => [['user_id' => 3]]], 'scores.0.score'],
    ]);
});

describe('tenant isolation and audit', function () {
    it('answers 404 for a class of another tenant and writes nothing', function () {
        $other = makeTenant();
        $foreignTeacher = makeTeacherUser($other);
        $foreignStudent = makeStudentUser($other);
        $foreignClass = makeClass($other, ['teacher' => $foreignTeacher, 'students' => [$foreignStudent->student]]);

        $this->withToken($this->token)->postJson("/api/integration/v1/classes/{$foreignClass->id}/grades", gradesPayload($this, [$foreignStudent->id => 80], 'assignment', $foreignTeacher->id))->assertNotFound();
        $this->withToken($this->token)->postJson('/api/integration/v1/classes/999999/grades', gradesPayload($this, [$this->s1->id => 80]))->assertNotFound();

        expect(Grade::count())->toBe(0);
    });

    it('records the audit on behalf of the lecturer with source lms', function () {
        $this->withToken($this->token)->postJson($this->url, gradesPayload($this, [$this->s1->id => 80, 987654 => 70], 'midterm'))->assertOk();

        $log = AuditLog::where('action', 'grades.integration_saved')->firstOrFail();

        expect($log->user_id)->toBe($this->teacherUser->id)
            ->and($log->tenant_id)->toBe($this->tenant->id)
            ->and($log->auditable_id)->toBe($this->class->id)
            ->and($log->new_values)->toMatchArray(['source' => 'lms', 'component' => 'midterm', 'updated' => 1, 'skipped' => 1]);
    });

    it('keeps the existing audit entries of the web UI attributed to the logged-in user', function () {
        $this->actingAs($this->teacherUser);
        app(GradingService::class)->saveScores($this->class, $this->teacherUser, [
            $this->s1->student->id => ['assignment' => 80, 'midterm' => 80, 'final' => 80],
            $this->s2->student->id => ['assignment' => 80, 'midterm' => 80, 'final' => 80],
        ]);
        app(GradingService::class)->lock($this->class, $this->teacherUser);

        expect(AuditLog::where('action', 'grades.locked')->value('user_id'))->toBe($this->teacherUser->id);
    });

    it('does not require a logged-in web session', function () {
        $this->withToken($this->token)->postJson($this->url, gradesPayload($this, [$this->s1->id => 80]))->assertOk();

        $this->assertGuest();
        expect(User::query()->count())->toBeGreaterThan(0);
    });
});
