<?php

use App\Enums\Role;
use App\Livewire\Students\Form;
use App\Models\AuditLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = makeTenant();
    $this->studentUser = makeStudentUser($this->tenant);
    $this->student = $this->studentUser->student;
});

function storePhoto(object $test, string $name = 'foto.png'): string
{
    $path = "students/photos/{$test->tenant->id}/{$test->student->id}.png";
    Storage::disk('local')->put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    $test->student->update(['photo_path' => $path]);

    return $path;
}

describe('serving the photo', function () {
    it('serves the photo to its owner and to academic staff', function () {
        storePhoto($this);

        foreach ([$this->studentUser, makeStaffUser($this->tenant, Role::Admin), makeStaffUser($this->tenant, Role::Curriculum), makeTeacherUser($this->tenant)] as $viewer) {
            $this->actingAs($viewer)->get(route('students.photo', $this->student))->assertOk()->assertHeader('Content-Type', 'image/png');
        }
    });

    it('refuses other students, finance, other campuses, guests, and students without a photo', function () {
        $this->actingAs(makeStudentUser($this->tenant))->get(route('students.photo', $this->student))->assertNotFound();
        $this->actingAs(makeStaffUser($this->tenant, Role::Admin))->get(route('students.photo', $this->student))->assertNotFound();

        storePhoto($this);

        $this->actingAs(makeStudentUser($this->tenant))->get(route('students.photo', $this->student))->assertNotFound();
        $this->actingAs(makeStaffUser($this->tenant, Role::Finance))->get(route('students.photo', $this->student))->assertNotFound();
        $this->actingAs(makeStaffUser(makeTenant(), Role::Admin))->get(route('students.photo', $this->student))->assertNotFound();

        auth()->logout();
        $this->get(route('students.photo', $this->student))->assertRedirect();
    });

    it('shows the photo on the student profile and falls back to initials', function () {
        $this->actingAs($this->studentUser)->get(route('profile.show'))->assertOk()->assertDontSee('students/'.$this->student->id.'/photo', false);

        storePhoto($this);

        $this->actingAs($this->studentUser)->get(route('profile.show'))->assertOk()->assertSee("/students/{$this->student->id}/photo", false);
    });
});

describe('manual upload by admin', function () {
    it('stores a JPG or PNG on the private disk and replaces an older photo', function () {
        $admin = makeStaffUser($this->tenant, Role::Admin);
        $old = storePhoto($this);

        Livewire::actingAs($admin)->test(Form::class, ['student' => $this->student])
            ->set('photo', UploadedFile::fake()->image('baru.jpg', 200, 200))->call('save')->assertHasNoErrors();

        $path = $this->student->refresh()->photo_path;

        expect($path)->toBe("students/photos/{$this->tenant->id}/{$this->student->id}.jpg");
        Storage::disk('local')->assertExists($path);
        Storage::disk('local')->assertMissing($old);
        expect(AuditLog::where('action', 'student.photo_uploaded')->count())->toBe(1);
    });

    it('rejects non-images, other formats, and files above 2 MB', function (UploadedFile $file) {
        Livewire::actingAs(makeStaffUser($this->tenant, Role::Admin))->test(Form::class, ['student' => $this->student])
            ->set('photo', $file)->assertHasErrors('photo');

        expect($this->student->refresh()->photo_path)->toBeNull();
    })->with([
        'pdf' => fn () => UploadedFile::fake()->create('foto.pdf', 10, 'application/pdf'),
        'gif' => fn () => UploadedFile::fake()->image('foto.gif'),
        'too big' => fn () => UploadedFile::fake()->image('besar.jpg')->size(2049),
    ]);

    it('keeps the photo when saving without a new file', function () {
        $path = storePhoto($this);

        Livewire::actingAs(makeStaffUser($this->tenant, Role::Admin))->test(Form::class, ['student' => $this->student])->call('save')->assertHasNoErrors();

        expect($this->student->refresh()->photo_path)->toBe($path);
        Storage::disk('local')->assertExists($path);
    });
});
