<?php

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Jobs\DeliverIntegrationEvent;
use App\Livewire\Groups\Show as GroupShow;
use App\Models\AcademicTerm;
use App\Models\Enrollment;
use App\Models\IntegrationEvent;
use App\Models\Student;
use App\Models\StudyGroupMember;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Services\EnrollmentService;
use App\Services\GradingService;
use App\Services\IntegrationEvents;
use App\Services\StudentStatusService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    // Data uji dibuat lebih dulu dengan integrasi mati agar tidak menimbulkan event; lihat enableWebhooks().
    $this->tenant = makeTenant();
    $this->term = makeTerm($this->tenant);
    $this->teacherUser = makeTeacherUser($this->tenant);
    $this->studentUser = makeStudentUser($this->tenant);
    $this->student = $this->studentUser->student;
    $this->class = makeClass($this->tenant, ['term' => $this->term, 'teacher' => $this->teacherUser, 'students' => [$this->student]]);
    $this->admin = makeStaffUser($this->tenant, Role::Admin);
});

function enableWebhooks(object $test): void
{
    config([
        'integration.lms.url' => 'http://aksaraloka.test',
        'integration.lms.tenant_id' => $test->tenant->id,
        'integration.lms.webhook_secret' => 'whsec',
    ]);
}

/**
 * @return array<int, array{string, array<string, mixed>}>
 */
function publishedEvents(): array
{
    return IntegrationEvent::query()->orderBy('created_at')->orderBy('type')->get()->map(fn (IntegrationEvent $event): array => [$event->type, $event->payload])->all();
}

describe('publishing', function () {
    it('does nothing when the integration is not configured', function (array $config) {
        Queue::fake();
        Http::fake();
        config($config);

        $this->term->update(['name' => 'Diubah']);
        $this->student->update(['status' => StudentStatus::Leave]);
        $result = app(IntegrationEvents::class)->publish('class.updated', ['class_id' => 1]);

        expect($result)->toBeNull()->and(IntegrationEvent::count())->toBe(0);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    })->with([
        'no url' => [['integration.lms.url' => '', 'integration.lms.tenant_id' => 1, 'integration.lms.webhook_secret' => 'x']],
        'no tenant' => [['integration.lms.url' => 'http://aksaraloka.test', 'integration.lms.tenant_id' => null, 'integration.lms.webhook_secret' => 'x']],
        'no secret' => [['integration.lms.url' => 'http://aksaraloka.test', 'integration.lms.tenant_id' => 1, 'integration.lms.webhook_secret' => '']],
    ]);

    it('stores the event and queues one delivery job for the integration tenant', function () {
        Queue::fake();
        enableWebhooks($this);

        $event = app(IntegrationEvents::class)->publish('class.updated', ['class_id' => 7], $this->tenant->id);

        expect($event->tenant_id)->toBe($this->tenant->id)->and($event->payload)->toBe(['class_id' => 7])->and($event->attempts)->toBe(0)->and($event->delivered_at)->toBeNull()
            ->and($event->id)->toMatch('/^[0-9a-f-]{36}$/');
        Queue::assertPushed(DeliverIntegrationEvent::class, fn (DeliverIntegrationEvent $job) => $job->eventId === $event->id && $job->afterCommit === true);
    });

    it('ignores data that belongs to another tenant', function () {
        Queue::fake();
        enableWebhooks($this);
        $other = makeTenant();

        expect(app(IntegrationEvents::class)->publish('class.updated', ['class_id' => 7], $other->id))->toBeNull();

        $otherTerm = makeTerm($other);
        $otherTerm->update(['name' => 'Diubah']);

        expect(IntegrationEvent::count())->toBe(0);
        Queue::assertNothingPushed();
    });

    it('merges identical pending events but not events that were already attempted', function () {
        Queue::fake();
        enableWebhooks($this);
        $events = app(IntegrationEvents::class);

        $first = $events->publish('roster.updated', ['class_id' => 3]);
        $again = $events->publish('roster.updated', ['class_id' => 3]);
        $other = $events->publish('roster.updated', ['class_id' => 4]);

        expect($again->id)->toBe($first->id)->and($other->id)->not->toBe($first->id)->and(IntegrationEvent::count())->toBe(2);

        $first->update(['attempts' => 1]);

        expect($events->publish('roster.updated', ['class_id' => 3])->id)->not->toBe($first->id);
    });

    it('stores nothing and sends nothing when the surrounding transaction rolls back', function () {
        Http::fake(['aksaraloka.test/*' => Http::response('', 200)]);
        enableWebhooks($this);

        try {
            DB::transaction(function () {
                app(IntegrationEvents::class)->publish('term.updated', ['term_id' => 1]);

                throw new RuntimeException('batal');
            });
        } catch (RuntimeException) {
        }

        expect(IntegrationEvent::count())->toBe(0);
        Http::assertNothingSent();
    });

    it('sends only after the surrounding transaction has committed', function () {
        Http::fake(['aksaraloka.test/*' => Http::response('', 200)]);
        enableWebhooks($this);

        DB::transaction(function () {
            app(IntegrationEvents::class)->publish('term.updated', ['term_id' => 1]);

            expect(Http::recorded())->toHaveCount(0)->and(IntegrationEvent::count())->toBe(1);
        });

        Http::assertSentCount(1);
        expect(IntegrationEvent::firstOrFail()->isDelivered())->toBeTrue();
    });

    it('delivers end to end after commit with the sync queue', function () {
        Http::fake(['aksaraloka.test/*' => Http::response(['status' => 'accepted'], 200)]);
        enableWebhooks($this);

        $this->student->update(['status' => StudentStatus::Leave]);

        $event = IntegrationEvent::firstOrFail();

        expect($event->type)->toBe('student.status_changed')->and($event->isDelivered())->toBeTrue()->and($event->attempts)->toBe(1);
        Http::assertSentCount(1);
    });
});

describe('delivery', function () {
    beforeEach(function () {
        enableWebhooks($this);
        $this->event = IntegrationEvent::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'roster.updated', 'payload' => ['class_id' => 12]]);
    });

    it('posts the contract body with signed headers to the LMS', function () {
        Http::fake(['aksaraloka.test/*' => Http::response('', 200)]);

        app(IntegrationEvents::class)->deliver($this->event);

        Http::assertSent(function (Request $request): bool {
            $body = $request->body();
            $timestamp = $request->header('X-Aksaraloka-Timestamp')[0];
            $decoded = json_decode($body, true);

            return $request->url() === 'http://aksaraloka.test/webhooks/academic'
                && $request->method() === 'POST'
                && $request->header('X-Aksaraloka-Event')[0] === 'roster.updated'
                && $request->header('X-Aksaraloka-Event-Id')[0] === $this->event->id
                && ctype_digit($timestamp) && abs(time() - (int) $timestamp) <= 5
                && $request->header('X-Aksaraloka-Signature')[0] === 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, 'whsec')
                && array_keys($decoded) === ['id', 'type', 'occurred_at', 'data']
                && $decoded['id'] === $this->event->id
                && $decoded['type'] === 'roster.updated'
                && $decoded['data'] === ['class_id' => 12]
                && str_starts_with($request->header('Content-Type')[0], 'application/json');
        });

        expect($this->event->refresh()->isDelivered())->toBeTrue()->and($this->event->attempts)->toBe(1)->and($this->event->last_error)->toBeNull();
    });

    it('produces a signature that a receiver using the documented formula accepts', function () {
        $timestamp = '1760000000';
        $body = '{"id":"x","type":"class.updated","occurred_at":"2026-10-16T10:00:00+07:00","data":{"class_id":1}}';

        expect(IntegrationEvents::signature($timestamp, $body, 'rahasia'))->toBe('sha256='.hash_hmac('sha256', $timestamp.'.'.$body, 'rahasia'))
            ->and(IntegrationEvents::signature($timestamp, $body, 'lain'))->not->toBe(IntegrationEvents::signature($timestamp, $body, 'rahasia'));
    });

    it('records a failed attempt with the error and keeps the event undelivered', function (callable $fake, string $error) {
        $fake();

        expect(fn () => app(IntegrationEvents::class)->deliver($this->event))->toThrow(RuntimeException::class);

        $this->event->refresh();
        expect($this->event->attempts)->toBe(1)->and($this->event->isDelivered())->toBeFalse()->and($this->event->last_error)->toContain($error);
    })->with([
        'server error' => [fn () => Http::fake(['aksaraloka.test/*' => Http::response('galat', 500)]), 'HTTP 500'],
        'bad signature' => [fn () => Http::fake(['aksaraloka.test/*' => Http::response('', 401)]), 'HTTP 401'],
        'unreachable' => [fn () => Http::fake(['aksaraloka.test/*' => fn () => throw new ConnectionException('timeout')]), 'Tidak dapat menghubungi LMS'],
    ]);

    it('counts attempts and clears the error once a retry succeeds', function () {
        Http::fake(['aksaraloka.test/*' => Http::sequence()->push('', 503)->push('', 502)->push('', 200)]);
        $events = app(IntegrationEvents::class);

        foreach ([1, 2] as $attempt) {
            expect(fn () => $events->deliver($this->event->refresh()))->toThrow(RuntimeException::class);
            expect($this->event->refresh()->attempts)->toBe($attempt);
        }

        $events->deliver($this->event->refresh());

        expect($this->event->refresh()->attempts)->toBe(3)->and($this->event->isDelivered())->toBeTrue()->and($this->event->last_error)->toBeNull();
    });

    it('never sends an event twice', function () {
        Http::fake(['aksaraloka.test/*' => Http::response('', 200)]);
        $events = app(IntegrationEvents::class);

        $events->deliver($this->event);
        $events->deliver($this->event->refresh());
        (new DeliverIntegrationEvent($this->event->id))->handle($events);

        Http::assertSentCount(1);
    });

    it('does not send when the integration has been switched off meanwhile', function () {
        Http::fake();
        config(['integration.lms.url' => '']);

        (new DeliverIntegrationEvent($this->event->id))->handle(app(IntegrationEvents::class));

        Http::assertNothingSent();
        expect($this->event->refresh()->attempts)->toBe(0);
    });
});

describe('job retry policy', function () {
    it('retries up to 8 attempts with backoff of 1, 5, 15, and then 60 minutes', function () {
        $job = new DeliverIntegrationEvent('x');

        expect($job->tries)->toBe(8)
            ->and($job->backoff())->toBe([60, 300, 900, 3600, 3600, 3600, 3600])
            ->and(count($job->backoff()))->toBe($job->tries - 1)
            ->and($job->afterCommit)->toBeTrue();
    });

    it('keeps the last error on the event when the queue gives up', function () {
        $event = IntegrationEvent::factory()->create(['tenant_id' => $this->tenant->id]);

        (new DeliverIntegrationEvent($event->id))->failed(new RuntimeException('LMS menjawab HTTP 500'));

        expect($event->refresh()->last_error)->toBe('LMS menjawab HTTP 500');
    });

    it('does not overwrite the error of an event that was delivered', function () {
        $event = IntegrationEvent::factory()->delivered()->create(['tenant_id' => $this->tenant->id]);

        (new DeliverIntegrationEvent($event->id))->failed(new RuntimeException('galat'));

        expect($event->refresh()->last_error)->toBeNull();
    });
});

describe('integration:events command', function () {
    it('lists the failures and does nothing without --retry-failed', function () {
        Queue::fake();
        enableWebhooks($this);
        $failed = IntegrationEvent::factory()->failed('HTTP 500')->create(['tenant_id' => $this->tenant->id]);
        IntegrationEvent::factory()->delivered()->create(['tenant_id' => $this->tenant->id]);
        IntegrationEvent::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->artisan('integration:events')->expectsOutputToContain('1 event terkirim, 1 menunggu, 1 gagal.')->expectsOutputToContain($failed->id)->assertSuccessful();

        Queue::assertNothingPushed();
        expect($failed->refresh()->attempts)->toBe(3);
    });

    it('re-sends only the failed events and resets their attempts', function () {
        Queue::fake();
        enableWebhooks($this);
        $failed = IntegrationEvent::factory()->failed()->create(['tenant_id' => $this->tenant->id]);
        $delivered = IntegrationEvent::factory()->delivered()->create(['tenant_id' => $this->tenant->id]);
        $pending = IntegrationEvent::factory()->create(['tenant_id' => $this->tenant->id]);
        $foreign = IntegrationEvent::factory()->failed()->create();

        $this->artisan('integration:events --retry-failed')->expectsOutputToContain('1 event dikirim ulang.')->assertSuccessful();

        Queue::assertPushed(DeliverIntegrationEvent::class, 1);
        Queue::assertPushed(DeliverIntegrationEvent::class, fn (DeliverIntegrationEvent $job) => $job->eventId === $failed->id);
        expect($failed->refresh()->attempts)->toBe(0)->and($delivered->refresh()->attempts)->toBe(1)->and($pending->refresh()->attempts)->toBe(0)->and($foreign->refresh()->attempts)->toBe(3);
    });

    it('delivers the retried event on the sync queue', function () {
        Http::fake(['aksaraloka.test/*' => Http::response('', 200)]);
        enableWebhooks($this);
        $failed = IntegrationEvent::factory()->failed()->create(['tenant_id' => $this->tenant->id]);

        $this->artisan('integration:events --retry-failed')->assertSuccessful();

        expect($failed->refresh()->isDelivered())->toBeTrue()->and($failed->attempts)->toBe(1);
    });

    it('refuses to retry without a webhook secret and explains when unconfigured', function () {
        config(['integration.lms.url' => 'http://aksaraloka.test', 'integration.lms.tenant_id' => $this->tenant->id, 'integration.lms.webhook_secret' => '']);
        $this->artisan('integration:events --retry-failed')->expectsOutputToContain('LMS_WEBHOOK_SECRET')->assertFailed();

        config(['integration.lms.url' => '']);
        $this->artisan('integration:events')->expectsOutputToContain('belum dikonfigurasi')->assertSuccessful();
    });
});

describe('event triggers', function () {
    beforeEach(function () {
        Queue::fake();
        enableWebhooks($this);
    });

    it('roster.updated when a KRS is approved, for the class and the other sections of the subject', function () {
        $visitorUser = makeStudentUser($this->tenant);
        $subject = $this->class->subject;
        $otherSection = makeClass($this->tenant, ['term' => $this->term, 'subject' => $subject, 'section' => 'B']);
        $unrelated = makeClass($this->tenant, ['term' => $this->term]);
        $enrollment = Enrollment::factory()->create(['tenant_id' => $this->tenant->id, 'academic_term_id' => $this->term->id, 'student_id' => $visitorUser->student->id, 'status' => EnrollmentStatus::Submitted]);
        $enrollment->assignments()->sync([$otherSection->id]);
        IntegrationEvent::query()->delete();

        $this->actingAs($this->admin);
        app(EnrollmentService::class)->decide($enrollment, $this->admin, true);

        $events = collect(publishedEvents());

        expect($events->pluck(0)->unique()->all())->toBe(['roster.updated'])
            ->and($events->map(fn ($e) => $e[1]['class_id'])->sort()->values()->all())->toBe(collect([$this->class->id, $otherSection->id])->sort()->values()->all())
            ->and($events->map(fn ($e) => $e[1]['class_id'])->all())->not->toContain($unrelated->id);
    });

    it('publishes nothing for a rejected KRS', function () {
        $enrollment = Enrollment::factory()->create(['tenant_id' => $this->tenant->id, 'academic_term_id' => $this->term->id, 'student_id' => $this->student->id, 'status' => EnrollmentStatus::Submitted]);
        $enrollment->assignments()->sync([$this->class->id]);
        IntegrationEvent::query()->delete();

        $this->actingAs($this->admin);
        app(EnrollmentService::class)->decide($enrollment, $this->admin, false, 'Tidak sesuai');

        expect(IntegrationEvent::count())->toBe(0);
    });

    it('grades.locked when the lecturer locks the grades', function () {
        app(GradingService::class)->saveScores($this->class, $this->teacherUser, [$this->student->id => ['assignment' => 80, 'midterm' => 80, 'final' => 80]]);
        $this->actingAs($this->teacherUser);

        app(GradingService::class)->lock($this->class, $this->teacherUser);

        expect(publishedEvents())->toBe([['grades.locked', ['class_id' => $this->class->id]]]);
    });

    it('student.status_changed when the student status changes, and only then', function () {
        $this->student->update(['phone' => '0812']);
        expect(IntegrationEvent::count())->toBe(0);

        $this->student->update(['status' => StudentStatus::Graduated]);

        expect(publishedEvents())->toBe([['student.status_changed', ['user_id' => $this->studentUser->id, 'status' => 'lulus']]]);
    });

    it('student.status_changed through the status service and the yudisium', function () {
        $service = app(StudentStatusService::class);

        $service->setStatus($this->student, $this->term, StudentStatus::Leave, 'Sakit', $this->admin);

        expect(publishedEvents())->toBe([['student.status_changed', ['user_id' => $this->studentUser->id, 'status' => 'cuti']]]);
    });

    it('does not announce a status change of a student without an account', function () {
        $noAccount = Student::factory()->create(['tenant_id' => $this->tenant->id, 'nim' => 'X1']);

        $noAccount->update(['status' => StudentStatus::Dropped]);

        expect(IntegrationEvent::count())->toBe(0);
    });

    it('class.updated when a class is created or its lecturer, section, or capacity changes', function () {
        $created = makeClass($this->tenant, ['term' => $this->term]);
        expect(publishedEvents())->toContain(['class.updated', ['class_id' => $created->id]]);

        IntegrationEvent::query()->delete();
        $created->update(['section' => 'Z']);
        $created->update(['capacity' => 10]);
        $created->update(['teacher_id' => makeTeacherUser($this->tenant)->teacher->id]);

        expect(publishedEvents())->toBe([['class.updated', ['class_id' => $created->id]]]);

        IntegrationEvent::query()->delete();
        $created->update(['hours_per_week' => 4]);
        expect(IntegrationEvent::count())->toBe(0);
    });

    it('class.deleted when a class is removed', function () {
        $victim = makeClass($this->tenant, ['term' => $this->term]);
        IntegrationEvent::query()->delete();

        $victim->delete();

        expect(publishedEvents())->toBe([['class.deleted', ['class_id' => $victim->id]]]);
    });

    it('class.updated for every class of a renamed subject or study group', function () {
        $subject = $this->class->subject;
        $extra = makeClass($this->tenant, ['term' => $this->term, 'subject' => $subject, 'section' => 'B']);
        IntegrationEvent::query()->delete();

        $subject->update(['name' => 'Nama Baru']);

        expect(collect(publishedEvents())->map(fn ($e) => $e[1]['class_id'])->sort()->values()->all())->toBe(collect([$this->class->id, $extra->id])->sort()->values()->all());

        IntegrationEvent::query()->delete();
        $subject->update(['semester' => 4]);
        $this->class->studyGroup->update(['name' => 'Kelas Baru']);

        expect(publishedEvents())->toBe([['class.updated', ['class_id' => $this->class->id]]]);
    });

    it('term.updated when a term is created or its schedule or lock state changes', function () {
        $created = makeTerm($this->tenant, ['name' => '2027/2028', 'is_active' => false]);
        expect(publishedEvents())->toBe([['term.updated', ['term_id' => $created->id]]]);

        IntegrationEvent::query()->delete();
        $created->update(['is_locked' => true]);
        $created->update(['grading_closes_on' => today()->addMonth()]);

        expect(publishedEvents())->toBe([['term.updated', ['term_id' => $created->id]]]);
    });

    it('term.updated for the previously active term when another term is activated', function () {
        $next = makeTerm($this->tenant, ['name' => '2027/2028', 'is_active' => false]);
        IntegrationEvent::query()->delete();

        $next->activate();

        expect(collect(publishedEvents())->map(fn ($e) => $e[1]['term_id'])->sort()->values()->all())->toBe(collect([$this->term->id, $next->id])->sort()->values()->all())
            ->and($this->term->refresh()->is_active)->toBeFalse();
    });

    it('user.updated when the account name, email, or role changes, but not for other fields', function () {
        $this->studentUser->update(['phone' => '0812', 'theme' => 'dark']);
        expect(IntegrationEvent::count())->toBe(0);

        $this->studentUser->update(['name' => 'Nama Baru']);
        $this->studentUser->update(['email' => 'baru@kampus.test']);

        expect(publishedEvents())->toBe([['user.updated', ['user_id' => $this->studentUser->id]]]);
    });

    it('user.updated when a student number or a lecturer NIDN changes', function () {
        $this->student->update(['nim' => 'BARU-1']);
        $this->teacherUser->teacher->update(['nidn' => '99887']);

        expect(collect(publishedEvents())->map(fn ($e) => $e[1]['user_id'])->sort()->values()->all())
            ->toBe(collect([$this->studentUser->id, $this->teacherUser->id])->sort()->values()->all());
    });

    it('user.updated for both lecturers when the homeroom lecturer of a group changes', function () {
        $group = $this->class->studyGroup;
        $first = makeTeacherUser($this->tenant);
        $second = makeTeacherUser($this->tenant);
        $group->update(['homeroom_teacher_id' => $first->teacher->id]);
        IntegrationEvent::query()->delete();

        $group->update(['homeroom_teacher_id' => $second->teacher->id]);

        expect(collect(publishedEvents())->map(fn ($e) => $e[1]['user_id'])->sort()->values()->all())->toBe(collect([$first->id, $second->id])->sort()->values()->all());
    });

    it('roster.updated for every class of the group when a student joins or leaves', function () {
        $group = $this->class->studyGroup;
        $second = makeClass($this->tenant, ['term' => $this->term, 'group' => $group]);
        $newcomer = makeStudentUser($this->tenant)->student;
        IntegrationEvent::query()->delete();

        joinGroup($group, $newcomer);

        $expected = collect([$this->class->id, $second->id])->sort()->values()->all();
        expect(collect(publishedEvents())->map(fn ($e) => $e[1]['class_id'])->sort()->values()->all())->toBe($expected);

        IntegrationEvent::query()->delete();
        StudyGroupMember::query()->where('student_id', $newcomer->id)->first()->update(['left_at' => today()]);

        expect(collect(publishedEvents())->map(fn ($e) => $e[1]['class_id'])->sort()->values()->all())->toBe($expected);
    });

    it('roster.updated when the admin removes a student from a group in the UI', function () {
        $group = $this->class->studyGroup;
        IntegrationEvent::query()->delete();

        Livewire::actingAs($this->admin)->test(GroupShow::class, ['group' => $group])->call('remove', $this->student->id);

        expect(publishedEvents())->toBe([['roster.updated', ['class_id' => $this->class->id]]])
            ->and(StudyGroupMember::query()->where('student_id', $this->student->id)->whereNull('left_at')->count())->toBe(0);
    });

    it('roster.updated when the admin places a student in a group in the UI', function () {
        $group = $this->class->studyGroup;
        $free = makeStudentUser($this->tenant)->student;
        IntegrationEvent::query()->delete();

        Livewire::actingAs($this->admin)->test(GroupShow::class, ['group' => $group])->set('selected', [$free->id])->call('place');

        expect(publishedEvents())->toBe([['roster.updated', ['class_id' => $this->class->id]]]);
    });

    it('publishes each distinct event once even for bulk changes', function () {
        $group = $this->class->studyGroup;

        foreach (range(1, 5) as $unused) {
            joinGroup($group, makeStudentUser($this->tenant)->student);
        }

        expect(IntegrationEvent::where('type', 'roster.updated')->count())->toBe(1);
    });

    it('queues one delivery job per stored event', function () {
        $this->student->update(['status' => StudentStatus::Leave]);
        $this->term->update(['name' => 'Baru']);

        Queue::assertPushed(DeliverIntegrationEvent::class, 2);
        expect(TeachingAssignment::query()->count())->toBeGreaterThan(0)->and(Subject::query()->count())->toBeGreaterThan(0)->and(AcademicTerm::query()->count())->toBe(1);
    });
});
