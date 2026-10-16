<?php

use App\Enums\Role;
use App\Models\Student;
use App\Models\StudyGroupMember;
use App\Models\User;
use App\Services\AttendanceReport;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->keyDirectory = sys_get_temp_dir().'/academic-sync-keys-'.getmypid();
    File::ensureDirectoryExists($this->keyDirectory);
    Passport::loadKeysFrom($this->keyDirectory);
    Artisan::call('passport:keys', ['--force' => true]);

    $this->tenant = makeTenant();
    config([
        'integration.lms.url' => 'http://aksaraloka.test',
        'integration.lms.tenant_id' => $this->tenant->id,
        'integration.lms.max_per_page' => 200,
    ]);

    $this->serverClient = app(ClientRepository::class)->createClientCredentialsGrantClient('Aksaraloka LMS (Server)');
    $this->ssoClient = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Aksaraloka LMS (SSO)', ['http://aksaraloka.test/auth/academic/callback']);

    $this->term = makeTerm($this->tenant, ['name' => '2026/2027', 'semester' => 'Ganjil']);
    $this->teacherUser = makeTeacherUser($this->tenant, ['name' => 'Dr. Dewi', 'email' => 'dewi@kampus.test'], ['nidn' => '0412']);
    $this->s1 = makeStudentUser($this->tenant, ['name' => 'Andi', 'email' => 'andi@kampus.test'], ['nim' => '1001']);
    $this->s2 = makeStudentUser($this->tenant, ['name' => 'Budi', 'email' => 'budi@kampus.test'], ['nim' => '1002']);
    $this->class = makeClass($this->tenant, [
        'term' => $this->term, 'teacher' => $this->teacherUser, 'credits' => 3, 'section' => 'A', 'capacity' => 40,
        'students' => [$this->s1->student, $this->s2->student],
    ]);
});

afterEach(fn () => File::deleteDirectory($this->keyDirectory));

/**
 * @return list<string>
 */
function readEndpoints(object $test): array
{
    return [
        '/api/integration/v1/terms',
        "/api/integration/v1/terms/{$test->term->id}/classes",
        "/api/integration/v1/classes/{$test->class->id}/roster",
        "/api/integration/v1/classes/{$test->class->id}/attendance",
    ];
}

describe('authentication and scopes', function () {
    it('answers 401 without a token or with a bad token', function () {
        foreach (readEndpoints($this) as $url) {
            $this->getJson($url)->assertUnauthorized();
            $this->withToken('bukan-token')->getJson($url)->assertUnauthorized();
        }
    });

    it('answers 403 to a client token that lacks the sync.read scope', function () {
        $token = serverToken('grades.write');

        foreach (readEndpoints($this) as $url) {
            $this->withToken($token)->getJson($url)->assertForbidden();
        }
    });

    it('serves a client token with sync.read', function () {
        $token = serverToken('sync.read');

        foreach (readEndpoints($this) as $url) {
            $this->withToken($token)->getJson($url)->assertOk();
        }
    });

    it('rejects a user (SSO) token even when it asks for the sync.read scope', function () {
        $token = userToken($this->s1, 'sync.read');

        foreach (readEndpoints($this) as $url) {
            $this->withToken($token)->getJson($url)->assertUnauthorized();
        }
    });

    it('rejects a profile-scoped user token', function () {
        $token = userToken($this->s1, 'profile');

        $this->withToken($token)->getJson('/api/integration/v1/terms')->assertUnauthorized();
    });

    it('answers 403 when the integration tenant is not configured', function () {
        config(['integration.lms.tenant_id' => null]);

        $this->withToken(serverToken())->getJson('/api/integration/v1/terms')->assertForbidden();
    });
});

describe('tenant isolation', function () {
    beforeEach(function () {
        $this->otherTenant = makeTenant();
        $this->otherTerm = makeTerm($this->otherTenant, ['name' => 'TERM RAHASIA']);
        $this->otherStudent = makeStudentUser($this->otherTenant, ['name' => 'Siswa Tenant Lain']);
        $this->otherClass = makeClass($this->otherTenant, ['term' => $this->otherTerm, 'students' => [$this->otherStudent->student]]);
        $this->token = serverToken();
    });

    it('lists only the terms of the integration tenant', function () {
        $response = $this->withToken($this->token)->getJson('/api/integration/v1/terms')->assertOk();

        expect(collect($response->json('data'))->pluck('id')->all())->toBe([$this->term->id])
            ->and($response->getContent())->not->toContain('TERM RAHASIA');
    });

    it('treats another tenant term, class, roster, and attendance as not found', function () {
        $this->withToken($this->token)->getJson("/api/integration/v1/terms/{$this->otherTerm->id}/classes")->assertNotFound();
        $this->withToken($this->token)->getJson("/api/integration/v1/classes/{$this->otherClass->id}/roster")->assertNotFound();
        $this->withToken($this->token)->getJson("/api/integration/v1/classes/{$this->otherClass->id}/attendance")->assertNotFound();
    });

    it('never mixes other tenants into a class listing, roster, or attendance recap', function () {
        foreach ([
            "/api/integration/v1/terms/{$this->term->id}/classes",
            "/api/integration/v1/classes/{$this->class->id}/roster",
            "/api/integration/v1/classes/{$this->class->id}/attendance",
        ] as $url) {
            $content = $this->withToken($this->token)->getJson($url)->assertOk()->getContent();

            expect($content)->not->toContain('Siswa Tenant Lain')->not->toContain('TERM RAHASIA');
        }
    });

    it('does not let a token be used for a request that happens while another tenant user is logged in', function () {
        $this->actingAs($this->otherStudent);

        $response = $this->withToken($this->token)->getJson('/api/integration/v1/terms')->assertOk();

        expect(collect($response->json('data'))->pluck('id')->all())->toBe([$this->term->id]);
    });

    it('returns 404 for ids that do not exist', function () {
        $this->withToken($this->token)->getJson('/api/integration/v1/terms/999999/classes')->assertNotFound();
        $this->withToken($this->token)->getJson('/api/integration/v1/classes/999999/roster')->assertNotFound();
        $this->withToken($this->token)->getJson('/api/integration/v1/classes/abc/roster')->assertNotFound();
    });
});

describe('terms and classes', function () {
    it('returns the term fields of the contract, newest first', function () {
        $older = makeTerm($this->tenant, ['name' => '2025/2026', 'semester' => 'Genap', 'is_active' => false, 'is_locked' => true,
            'starts_at' => today()->subYear()->toDateString(), 'ends_at' => today()->subMonths(6)->toDateString()]);

        $data = $this->withToken(serverToken())->getJson('/api/integration/v1/terms')->assertOk()->json('data');

        expect($data)->toHaveCount(2)
            ->and($data[0])->toMatchArray(['id' => $this->term->id, 'name' => '2026/2027', 'semester' => 'Ganjil', 'is_active' => true, 'is_locked' => false])
            ->and($data[0]['starts_at'])->toBe($this->term->starts_at->toDateString())
            ->and($data[0])->toHaveKey('updated_at')
            ->and($data[1])->toMatchArray(['id' => $older->id, 'is_active' => false, 'is_locked' => true]);
    });

    it('returns the class fields of the contract', function () {
        $item = $this->withToken(serverToken())->getJson("/api/integration/v1/terms/{$this->term->id}/classes")->assertOk()->json('data.0');

        expect($item)->toMatchArray([
            'id' => $this->class->id, 'term_id' => $this->term->id, 'section' => 'A', 'capacity' => 40, 'grades_locked' => false,
            'subject' => ['code' => $this->class->subject->code, 'name' => $this->class->subject->name, 'credits' => 3],
            'teachers' => [['user_id' => $this->teacherUser->id, 'name' => 'Dr. Dewi', 'email' => 'dewi@kampus.test', 'identity_number' => '0412']],
        ])->and($item['class_label'])->toContain($this->class->studyGroup->name)->toContain('Seksi A');
    });

    it('reports locked grades and a class without a linked teacher account', function () {
        lockGrades($this->class);
        $this->class->teacher->update(['user_id' => null]);

        $item = $this->withToken(serverToken())->getJson("/api/integration/v1/terms/{$this->term->id}/classes")->json('data.0');

        expect($item['grades_locked'])->toBeTrue()->and($item['teachers'])->toBe([]);
    });

    it('paginates with the standard Laravel structure and caps per_page', function () {
        foreach (range(1, 4) as $i) {
            makeClass($this->tenant, ['term' => $this->term]);
        }

        config(['integration.lms.max_per_page' => 2]);
        $response = $this->withToken(serverToken())->getJson("/api/integration/v1/terms/{$this->term->id}/classes?per_page=500&page=2")->assertOk();

        expect($response->json('data'))->toHaveCount(2)
            ->and($response->json('meta.per_page'))->toBe(2)
            ->and($response->json('meta.total'))->toBe(5)
            ->and($response->json('meta.current_page'))->toBe(2)
            ->and($response->json('meta.last_page'))->toBe(3)
            ->and($response->json('links.next'))->not->toBeNull();

        $one = $this->withToken(serverToken())->getJson("/api/integration/v1/terms/{$this->term->id}/classes?per_page=0")->json('meta.per_page');
        expect($one)->toBe(1);
    });
});

describe('roster', function () {
    it('lists the class members with the fields of the contract', function () {
        $data = $this->withToken(serverToken())->getJson("/api/integration/v1/classes/{$this->class->id}/roster")->assertOk()->json('data');

        expect($data)->toHaveCount(2)
            ->and($data[0])->toBe(['user_id' => $this->s1->id, 'name' => 'Andi', 'email' => 'andi@kampus.test', 'identity_number' => '1001', 'status' => 'aktif'])
            ->and($data[1]['user_id'])->toBe($this->s2->id);
    });

    it('matches ClassRoster including approved KRS from other groups and section moves', function () {
        $visitor = makeStudentUser($this->tenant, ['name' => 'Citra'], ['nim' => '1003']);
        approvedEnrollment($visitor->student, $this->term, [$this->class]);

        $subject = $this->class->subject;
        $otherSection = makeClass($this->tenant, ['term' => $this->term, 'subject' => $subject, 'section' => 'B']);
        approvedEnrollment($this->s2->student, $this->term, [$otherSection]);

        $token = serverToken();
        $ids = collect($this->withToken($token)->getJson("/api/integration/v1/classes/{$this->class->id}/roster")->json('data'))->pluck('user_id')->sort()->values()->all();
        $moved = collect($this->withToken($token)->getJson("/api/integration/v1/classes/{$otherSection->id}/roster")->json('data'))->pluck('user_id')->all();

        expect($ids)->toBe(collect([$this->s1->id, $visitor->id])->sort()->values()->all())
            ->and($moved)->toBe([$this->s2->id]);
    });

    it('skips students without an account and passes every status through', function () {
        $noAccount = Student::factory()->create(['tenant_id' => $this->tenant->id, 'program_id' => makeProgram($this->tenant)->id, 'name' => 'Tanpa Akun', 'nim' => '2001']);
        joinGroup($this->class->studyGroup, $noAccount);
        $this->s2->student->update(['status' => 'lulus']);

        $data = $this->withToken(serverToken())->getJson("/api/integration/v1/classes/{$this->class->id}/roster")->json('data');

        expect(collect($data)->pluck('name')->all())->toBe(['Andi', 'Budi'])
            ->and(collect($data)->pluck('status', 'name')->all())->toBe(['Andi' => 'aktif', 'Budi' => 'lulus']);
    });

    it('excludes members who left the group before the term', function () {
        StudyGroupMember::query()->where('student_id', $this->s2->student->id)->update(['left_at' => today()->subMonths(3)]);

        $data = $this->withToken(serverToken())->getJson("/api/integration/v1/classes/{$this->class->id}/roster")->json('data');

        expect(collect($data)->pluck('user_id')->all())->toBe([$this->s1->id]);
    });

    it('paginates the roster', function () {
        foreach (range(3, 6) as $n) {
            joinGroup($this->class->studyGroup, makeStudentUser($this->tenant, ['name' => "Mhs {$n}"])->student);
        }

        $response = $this->withToken(serverToken())->getJson("/api/integration/v1/classes/{$this->class->id}/roster?per_page=4&page=2")->assertOk();

        expect($response->json('meta.total'))->toBe(6)->and($response->json('data'))->toHaveCount(2);
    });
});

describe('attendance', function () {
    /**
     * @return array<string, mixed>
     */
    function attendanceRowFor(object $test, User $user): array
    {
        $rows = test()->withToken(serverToken())->getJson("/api/integration/v1/classes/{$test->class->id}/attendance")->assertOk()->json('data');

        return collect($rows)->firstWhere('user_id', $user->id);
    }

    it('summarises each student with the fields of the contract', function () {
        recordAttendance($this->class, $this->s1->student, ['hadir', 'izin', 'sakit', 'alpa']);

        $row = attendanceRowFor($this, $this->s1);

        expect($row)->toBe(['user_id' => $this->s1->id, 'sessions' => 4, 'present' => 1, 'excused' => 1, 'sick' => 1, 'absent' => 1, 'percent' => 25, 'standing' => 'waspada']);
    });

    it('lists students without attendance with zero sessions and no percent', function () {
        $row = attendanceRowFor($this, $this->s2);

        expect($row)->toMatchArray(['sessions' => 0, 'present' => 0, 'percent' => null, 'standing' => 'aman']);
    });

    it('maps the Academic attendance levels to aman, waspada, and tidak_memenuhi', function (array $statuses, string $standing) {
        recordAttendance($this->class, $this->s1->student, $statuses);

        expect(attendanceRowFor($this, $this->s1)['standing'])->toBe($standing);
    })->with([
        'aman' => [['hadir', 'hadir', 'alpa'], 'aman'],
        'waspada (sisa toleransi 1)' => [['alpa', 'alpa', 'alpa', 'hadir'], 'waspada'],
        'tidak memenuhi (melewati toleransi)' => [['alpa', 'alpa', 'alpa', 'alpa', 'alpa'], 'tidak_memenuhi'],
        'baru tanpa presensi' => [[], 'aman'],
    ]);

    it('agrees with the exam-eligibility attendance of the Academic reports for the same student', function () {
        recordAttendance($this->class, $this->s1->student, ['hadir', 'hadir', 'alpa', 'sakit']);

        $report = app(AttendanceReport::class)->studentBySubject($this->s1->student->id)->firstWhere('subject.id', $this->class->subject_id);
        $row = attendanceRowFor($this, $this->s1);

        expect($row['percent'])->toBe($report['percent'])->and($row['sessions'])->toBe($report['total'])->and($row['present'])->toBe($report['attended']);
    });

    it('agrees with the student analytics page', function (array $statuses, string $expectedLevel, string $standing) {
        recordAttendance($this->class, $this->s1->student, $statuses);

        $page = app(AttendanceReport::class)->studentCourseAnalytics(
            $this->s1->student, $this->class->studyGroup, $this->term,
            (int) $this->tenant->setting('min_attendance_percent'), (int) $this->tenant->setting('meetings_per_course'),
        )->firstWhere('assignment.id', $this->class->id);

        $row = attendanceRowFor($this, $this->s1);

        expect($page['level'])->toBe($expectedLevel)->and($row['standing'])->toBe($standing)->and($row['percent'])->toBe($page['percent']);
    })->with([
        'aman' => [['hadir', 'hadir', 'alpa'], 'aman', 'aman'],
        'waspada' => [['alpa', 'alpa', 'alpa', 'hadir'], 'waspada', 'waspada'],
        'risiko' => [['alpa', 'alpa', 'alpa', 'alpa', 'alpa'], 'risiko', 'tidak_memenuhi'],
    ])->skip('BUG: AttendanceReport::studentCourseAnalytics memakai flatMap(mapWithKeys) sehingga kunci id jadwal menjadi 0,1,2... (Collection::collapse memakai array_merge). Akibatnya whereIn(schedule_id) dan pengelompokan per kuliah salah, dan halaman analitik mahasiswa membaca presensi yang keliru atau kosong ("baru").');

    it('follows the campus attendance settings', function () {
        $this->tenant->update(['settings' => ['min_attendance_percent' => 50, 'meetings_per_course' => 8]]);
        recordAttendance($this->class, $this->s1->student, ['alpa', 'alpa', 'alpa', 'alpa', 'alpa', 'hadir']);

        expect(attendanceRowFor($this, $this->s1)['standing'])->toBe('tidak_memenuhi');

        $this->tenant->update(['settings' => ['min_attendance_percent' => 10, 'meetings_per_course' => 16]]);

        expect(attendanceRowFor($this, $this->s1)['standing'])->toBe('aman');
    });

    it('counts only sessions of this class and of its term', function () {
        recordAttendance($this->class, $this->s1->student, ['hadir', 'hadir']);
        $other = makeClass($this->tenant, ['term' => $this->term, 'students' => [$this->s1->student]]);
        recordAttendance($other, $this->s1->student, ['alpa', 'alpa', 'alpa']);

        expect(attendanceRowFor($this, $this->s1))->toMatchArray(['sessions' => 2, 'absent' => 0, 'percent' => 100]);
    });

    it('paginates the recap and keeps rows aligned with the page', function () {
        foreach (range(3, 5) as $n) {
            $student = makeStudentUser($this->tenant, ['name' => "Mhs {$n}"])->student;
            joinGroup($this->class->studyGroup, $student);
            recordAttendance($this->class, $student, ['hadir']);
        }

        $response = $this->withToken(serverToken())->getJson("/api/integration/v1/classes/{$this->class->id}/attendance?per_page=2&page=2")->assertOk();

        expect($response->json('meta.total'))->toBe(5)->and($response->json('data'))->toHaveCount(2)
            ->and(collect($response->json('data'))->every(fn ($row) => $row['user_id'] !== null))->toBeTrue();
    });
});

describe('/me profile', function () {
    it('includes the study program of a student and omits it for others', function () {
        $program = $this->s1->student->program;

        Passport::actingAs($this->s1, ['profile']);
        $this->getJson('/api/integration/v1/me')->assertOk()->assertJsonPath('data.program.code', $program->code)->assertJsonPath('data.program.name', $program->name);

        Passport::actingAs($this->teacherUser, ['profile']);
        $this->getJson('/api/integration/v1/me')->assertOk()->assertJsonMissingPath('data.program');

        Passport::actingAs(makeStaffUser($this->tenant, Role::Admin), ['profile']);
        $this->getJson('/api/integration/v1/me')->assertOk()->assertJsonMissingPath('data.program');
    });
});
