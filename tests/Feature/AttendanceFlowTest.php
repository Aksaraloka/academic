<?php

use App\Enums\AttendanceMode;
use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Livewire\Attendance\Mark;
use App\Models\AttendanceRecord;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Tenant;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/**
 * Siapkan kampus dengan satu kelas berisi tiga mahasiswa dan satu jadwal hari ini.
 *
 * @return array{tenant: Tenant, teacher: Teacher, user: User, group: StudyGroup, students: Collection, schedule: Schedule}
 */
function schoolWithClass(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->role(Role::Teacher)->create();
    $teacher = Teacher::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'name' => $user->name]);
    $group = StudyGroup::factory()->create(['tenant_id' => $tenant->id, 'homeroom_teacher_id' => $teacher->id, 'name' => '7A']);

    $students = Student::factory()->count(3)->create(['tenant_id' => $tenant->id])->each(
        fn (Student $student) => $group->members()->create(['student_id' => $student->id, 'joined_at' => today()]),
    );

    $slot = TimeSlot::create(['tenant_id' => $tenant->id, 'day' => now()->dayOfWeekIso, 'period_number' => 1, 'starts_at' => '07:00', 'ends_at' => '07:40']);
    $subject = Subject::create(['tenant_id' => $tenant->id, 'name' => 'Matematika']);
    $assignment = TeachingAssignment::create([
        'tenant_id' => $tenant->id, 'academic_term_id' => $group->academic_term_id,
        'teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'study_group_id' => $group->id,
    ]);
    $schedule = Schedule::create(['tenant_id' => $tenant->id, 'teaching_assignment_id' => $assignment->id, 'time_slot_id' => $slot->id]);

    return compact('tenant', 'teacher', 'user', 'group', 'students', 'schedule');
}

it('opens a session with every student defaulting to present', function () {
    $school = schoolWithClass();
    $this->actingAs($school['user']);

    $session = app(AttendanceService::class)->openSession($school['schedule'], $school['user']);

    expect($session->records)->toHaveCount(3)
        ->and($session->records->every(fn ($record) => $record->status === AttendanceStatus::Present))->toBeTrue();
});

it('lets a teacher mark absent students with a tap and save', function () {
    $school = schoolWithClass();
    $this->actingAs($school['user']);
    $session = app(AttendanceService::class)->openSession($school['schedule'], $school['user']);
    $absentee = $school['students']->first();

    Livewire::test(Mark::class, ['session' => $session])
        ->call('toggleAbsent', $absentee->id)
        ->call('save')
        ->assertDispatched('toast');

    expect(AttendanceRecord::where('student_id', $absentee->id)->first()->status)->toBe(AttendanceStatus::Absent);
});

it('only lets homeroom teachers or admins correct attendance after the day has passed', function () {
    $school = schoolWithClass();
    $this->actingAs($school['user']);
    $session = app(AttendanceService::class)->openSession($school['schedule'], $school['user']);
    $session->update(['date' => today()->subDay()]);

    $otherUser = User::factory()->for($school['tenant'])->role(Role::Teacher)->create();
    Teacher::factory()->create(['tenant_id' => $school['tenant']->id, 'user_id' => $otherUser->id]);

    $service = app(AttendanceService::class);
    $session->refresh();

    expect($service->canEdit($session, $otherUser))->toBeFalse()
        ->and($service->canEdit($session, $school['user']))->toBeTrue();
});

it('logs corrections made after the day of the session', function () {
    $school = schoolWithClass();
    $this->actingAs($school['user']);
    $service = app(AttendanceService::class);
    $session = $service->openSession($school['schedule'], $school['user']);
    $session->update(['date' => today()->subDay()]);

    $service->saveStatuses($session->refresh(), $school['user'], [$school['students']->first()->id => 'sakit']);

    $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.corrected']);
});

it('lets only teachers record attendance and never students themselves', function () {
    $school = schoolWithClass();
    $this->actingAs($school['user']);
    $session = app(AttendanceService::class)->openSession($school['schedule'], $school['user']);
    $session->records()->update(['status' => AttendanceStatus::Absent]);

    $studentUser = User::factory()->for($school['tenant'])->role(Role::Student)->create();
    $school['students']->first()->update(['user_id' => $studentUser->id]);

    $this->actingAs($studentUser)->get(route('attendance.mark', $session))->assertForbidden();
    $this->actingAs($studentUser)->get(route('attendance.index'))->assertForbidden();
    $this->actingAs($studentUser)->get('/pindai')->assertNotFound();
    $this->actingAs($studentUser)->get("/presensi/{$session->id}/qr")->assertNotFound();

    expect(method_exists(AttendanceService::class, 'scanQr'))->toBeFalse()
        ->and(Route::has('scan'))->toBeFalse()
        ->and(Route::has('attendance.qr'))->toBeFalse()
        ->and($session->records()->where('status', AttendanceStatus::Present)->count())->toBe(0);
});

it('has no QR attendance mode left', function () {
    expect(collect(AttendanceMode::cases())->pluck('value')->all())->not->toContain('qr');
});
