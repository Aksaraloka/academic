<?php

use App\Enums\Role;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\StudyGroup;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Tenant;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\ScheduleConflictDetector;
use App\Services\TeacherCheckinService;
use Illuminate\Validation\ValidationException;

it('detects teacher, class and room conflicts in the same time slot', function () {
    $tenant = Tenant::factory()->create();
    $teacher = Teacher::factory()->create(['tenant_id' => $tenant->id]);
    $groupA = StudyGroup::factory()->create(['tenant_id' => $tenant->id, 'name' => '7A']);
    $groupB = StudyGroup::factory()->create(['tenant_id' => $tenant->id, 'academic_term_id' => $groupA->academic_term_id, 'name' => '7B']);
    $subject = Subject::create(['tenant_id' => $tenant->id, 'name' => 'IPA']);
    $room = Room::create(['tenant_id' => $tenant->id, 'name' => 'Lab']);
    $slot = TimeSlot::create(['tenant_id' => $tenant->id, 'day' => 1, 'period_number' => 1, 'starts_at' => '07:00', 'ends_at' => '07:40']);

    $make = fn (StudyGroup $group, Teacher $teacher) => TeachingAssignment::create([
        'tenant_id' => $tenant->id, 'academic_term_id' => $group->academic_term_id,
        'teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'study_group_id' => $group->id,
    ]);

    $first = $make($groupA, $teacher);
    Schedule::create(['tenant_id' => $tenant->id, 'teaching_assignment_id' => $first->id, 'time_slot_id' => $slot->id, 'room_id' => $room->id]);

    $detector = app(ScheduleConflictDetector::class);

    expect($detector->conflictsFor($make($groupB, $teacher), $slot->id, null))->toHaveCount(1)
        ->and($detector->conflictsFor($make($groupA, Teacher::factory()->create(['tenant_id' => $tenant->id])), $slot->id, null))->toHaveCount(1)
        ->and($detector->conflictsFor($make($groupB, Teacher::factory()->create(['tenant_id' => $tenant->id])), $slot->id, $room->id))->toHaveCount(1)
        ->and($detector->conflictsFor($make($groupB, Teacher::factory()->create(['tenant_id' => $tenant->id])), $slot->id, null))->toBeEmpty();
});

it('accepts check-in inside the geofence and rejects outside it', function () {
    $this->travelTo(now('Asia/Jakarta')->setTime(7, 0));
    $tenant = Tenant::factory()->create(['settings' => ['geofence_lat' => -6.2, 'geofence_lng' => 106.8, 'geofence_radius' => 100]]);
    $teacher = Teacher::factory()->create(['tenant_id' => $tenant->id]);
    $service = app(TeacherCheckinService::class);

    expect(fn () => $service->checkIn($teacher, $tenant, -6.21, 106.8, 12))->toThrow(ValidationException::class);

    $checkin = $service->checkIn($teacher, $tenant, -6.2002, 106.8001, 12);
    expect($checkin->check_in_at)->not->toBeNull()->and($checkin->flagged)->toBeFalse();

    expect(fn () => $service->checkIn($teacher, $tenant, -6.2002, 106.8001, 12))->toThrow(ValidationException::class);
});

it('flags suspiciously perfect GPS accuracy instead of rejecting it', function () {
    $this->travelTo(now('Asia/Jakarta')->setTime(7, 0));
    $tenant = Tenant::factory()->create(['settings' => ['geofence_lat' => -6.2, 'geofence_lng' => 106.8]]);
    $teacher = Teacher::factory()->create(['tenant_id' => $tenant->id]);

    $checkin = app(TeacherCheckinService::class)->checkIn($teacher, $tenant, -6.2, 106.8, 0.0);

    expect($checkin->flagged)->toBeTrue();
});

it('rejects check-in outside the allowed hours', function () {
    $this->travelTo(now('Asia/Jakarta')->setTime(2, 0));
    $tenant = Tenant::factory()->create(['settings' => ['geofence_lat' => -6.2, 'geofence_lng' => 106.8]]);
    $teacher = Teacher::factory()->create(['tenant_id' => $tenant->id]);

    expect(fn () => app(TeacherCheckinService::class)->checkIn($teacher, $tenant, -6.2, 106.8, 10))
        ->toThrow(ValidationException::class);
});

it('keeps role as an enum on users', function () {
    $user = User::factory()->role(Role::Principal)->create();

    expect($user->role)->toBe(Role::Principal)->and($user->hasRole(Role::Admin))->toBeFalse();
});
