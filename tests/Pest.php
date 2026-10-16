<?php

use App\Enums\AcademicEventType;
use App\Enums\EnrollmentStatus;
use App\Enums\GradeKind;
use App\Enums\Role;
use App\Models\AcademicEvent;
use App\Models\AcademicTerm;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\Grade;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\StudyGroupMember;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\GradeScale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => Storage::fake('local'))
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Kampus baru yang terisolasi dari data seeder. Isi `tenant_id` selalu eksplisit di helper berikut.
 *
 * @param  array<string, mixed>  $attributes
 */
function makeTenant(array $attributes = []): Tenant
{
    return Tenant::factory()->create($attributes);
}

function makeProgram(Tenant $tenant, string $name = 'Informatika'): Program
{
    $faculty = Faculty::query()->withoutGlobalScopes()->firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Fakultas Teknik']);

    return Program::query()->withoutGlobalScopes()->firstOrCreate(
        ['tenant_id' => $tenant->id, 'name' => $name],
        ['faculty_id' => $faculty->id, 'code' => strtoupper(substr($name, 0, 3)), 'degree' => 'S1'],
    );
}

/**
 * Semester aktif yang mencakup hari ini.
 *
 * @param  array<string, mixed>  $attributes
 */
function makeTerm(Tenant $tenant, array $attributes = []): AcademicTerm
{
    return AcademicTerm::factory()->create($attributes + [
        'tenant_id' => $tenant->id,
        'starts_at' => today()->subMonth()->toDateString(),
        'ends_at' => today()->addMonths(3)->toDateString(),
    ]);
}

/**
 * Pengguna mahasiswa lengkap dengan data Student (program, NIM unik).
 *
 * @param  array<string, mixed>  $userAttributes
 * @param  array<string, mixed>  $studentAttributes
 */
function makeStudentUser(Tenant $tenant, array $userAttributes = [], array $studentAttributes = []): User
{
    static $sequence = 0;
    $sequence++;

    $user = User::factory()->role(Role::Student)->create($userAttributes + ['tenant_id' => $tenant->id]);

    Student::factory()->create($studentAttributes + [
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'program_id' => makeProgram($tenant)->id,
        'name' => $user->name,
        'nim' => 'M'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
        'batch_year' => 2023,
        'entry_date' => today()->subYears(2)->toDateString(),
    ]);

    return $user->load('student');
}

/**
 * Pengguna dosen lengkap dengan data Teacher.
 *
 * @param  array<string, mixed>  $userAttributes
 * @param  array<string, mixed>  $teacherAttributes
 */
function makeTeacherUser(Tenant $tenant, array $userAttributes = [], array $teacherAttributes = []): User
{
    $user = User::factory()->role(Role::Teacher)->create($userAttributes + ['tenant_id' => $tenant->id]);

    Teacher::factory()->create($teacherAttributes + ['tenant_id' => $tenant->id, 'user_id' => $user->id, 'name' => $user->name]);

    return $user->load('teacher');
}

function makeStaffUser(Tenant $tenant, Role $role): User
{
    return User::factory()->role($role)->create(['tenant_id' => $tenant->id]);
}

/**
 * Masukkan mahasiswa ke kelas angkatan (anggota aktif).
 */
function joinGroup(StudyGroup $group, Student $student): void
{
    StudyGroupMember::create(['study_group_id' => $group->id, 'student_id' => $student->id, 'joined_at' => today()->subMonths(6)]);
}

/**
 * Satu kuliah (penugasan) lengkap: mata kuliah, kelas angkatan, dan dosen. Opsi: term, program, teacher (User),
 * group, credits, semester, section, capacity, subject (Subject yang sudah ada), students (daftar Student).
 *
 * @param  array<string, mixed>  $options
 */
function makeClass(Tenant $tenant, array $options = []): TeachingAssignment
{
    static $sequence = 0;
    $sequence++;

    $term = $options['term'] ?? makeTerm($tenant);
    $program = $options['program'] ?? makeProgram($tenant);
    $teacher = ($options['teacher'] ?? makeTeacherUser($tenant))->teacher;

    $group = $options['group'] ?? StudyGroup::factory()->create([
        'tenant_id' => $tenant->id, 'academic_term_id' => $term->id, 'program_id' => $program->id, 'name' => 'IF-'.$sequence,
    ]);

    $subject = $options['subject'] ?? Subject::create([
        'tenant_id' => $tenant->id, 'program_id' => $program->id, 'name' => 'Mata Kuliah '.$sequence, 'code' => 'MK'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
        'credits' => $options['credits'] ?? 3, 'semester' => $options['semester'] ?? 1,
    ]);

    $assignment = TeachingAssignment::create([
        'tenant_id' => $tenant->id, 'academic_term_id' => $term->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id,
        'study_group_id' => $group->id, 'section' => $options['section'] ?? null, 'capacity' => $options['capacity'] ?? null,
    ]);

    foreach ($options['students'] ?? [] as $student) {
        joinGroup($group, $student);
    }

    return $assignment->load(['teacher.user', 'subject', 'studyGroup', 'academicTerm']);
}

/**
 * Buka masa KRS yang mencakup hari ini untuk semester itu.
 */
function openEnrollmentWindow(AcademicTerm $term): AcademicEvent
{
    return AcademicEvent::create([
        'tenant_id' => $term->tenant_id, 'academic_term_id' => $term->id, 'type' => AcademicEventType::Enrollment,
        'starts_on' => today()->subDay()->toDateString(), 'ends_on' => today()->addWeek()->toDateString(),
    ]);
}

/**
 * Tulis nilai reguler langsung (tanpa lewat layanan) dengan total dan huruf yang konsisten dengan skala kampus.
 */
function giveGrade(TeachingAssignment $assignment, Student $student, float $total, GradeKind $kind = GradeKind::Regular): Grade
{
    $scale = GradeScale::for($assignment->tenant ?? Tenant::findOrFail($assignment->tenant_id));
    $letter = $scale->letterFor($total);

    return Grade::create([
        'tenant_id' => $assignment->tenant_id, 'teaching_assignment_id' => $assignment->id, 'student_id' => $student->id, 'kind' => $kind,
        'assignment_score' => $total, 'midterm_score' => $total, 'final_exam_score' => $total, 'attendance_score' => $total,
        'total_score' => $total, 'letter' => $letter, 'points' => GradeScale::pointsFor($letter),
    ]);
}

function lockGrades(TeachingAssignment $assignment): void
{
    $assignment->update(['grades_locked_at' => now()]);
}

/**
 * KRS yang sudah disetujui berisi penugasan tertentu.
 *
 * @param  array<int, TeachingAssignment>  $assignments
 */
function approvedEnrollment(Student $student, AcademicTerm $term, array $assignments): Enrollment
{
    $enrollment = Enrollment::query()->withoutGlobalScopes()->updateOrCreate(
        ['academic_term_id' => $term->id, 'student_id' => $student->id],
        ['tenant_id' => $student->tenant_id, 'status' => EnrollmentStatus::Approved, 'submitted_at' => now(), 'decided_at' => now()],
    );
    $enrollment->assignments()->sync(collect($assignments)->pluck('id')->all());

    return $enrollment;
}
