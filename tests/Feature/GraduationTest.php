<?php

use App\Enums\GraduationStatus;
use App\Livewire\Graduation\Minutes;
use App\Livewire\Graduation\Periods;
use App\Livewire\Graduation\Register;
use App\Livewire\Graduation\Registrations;
use App\Models\GraduationPeriod;
use App\Models\GraduationRegistration;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Services\GraduationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->period = GraduationPeriod::firstOrFail();
    $this->admin = User::where('email', 'admin@aksaraloka.test')->firstOrFail();
});

/** Mahasiswa contoh yang belum mendaftar, dengan akun login. */
function candidateUser(): User
{
    $user = User::where('email', 'mahasiswa@aksaraloka.test')->firstOrFail();
    $user->student->update(['program_id' => Program::first()->id]);

    return $user;
}

it('renders the wisuda home for every allowed role and blocks others', function () {
    foreach (['admin@', 'kaprodi@', 'akademik@', 'mahasiswa@'] as $prefix) {
        $this->actingAs(User::where('email', $prefix.'aksaraloka.test')->firstOrFail())
            ->get('/graduation')->assertOk()->assertSee('Wisuda Periode II Tahun 2026');
    }

    $this->actingAs(User::where('email', 'dosen@aksaraloka.test')->firstOrFail())->get('/graduation')->assertForbidden();
    $this->actingAs(User::where('email', 'ortu@aksaraloka.test')->firstOrFail())->get('/graduation')->assertForbidden();
});

it('hides unpublished periods from students', function () {
    $this->period->update(['is_published' => false]);

    $this->actingAs(candidateUser())->get('/graduation')->assertOk()->assertDontSee('Wisuda Periode II Tahun 2026');
});

it('registers a student and rejects values below the requirements', function () {
    $user = candidateUser();

    Livewire::actingAs($user)->test(Register::class, ['period' => $this->period])
        ->set('thesis_title', 'Sistem Presensi Berbasis QR')->set('gpa', '1.80')->set('credits_earned', '150')->set('defense_date', today()->subDays(3)->toDateString())
        ->call('save')->assertHasErrors('gpa');

    Livewire::actingAs($user)->test(Register::class, ['period' => $this->period])
        ->set('thesis_title', 'Sistem Presensi Berbasis QR')->set('gpa', '3.50')->set('credits_earned', '120')->set('defense_date', today()->subDays(3)->toDateString())
        ->call('save')->assertHasErrors('credits_earned');

    Livewire::actingAs($user)->test(Register::class, ['period' => $this->period])
        ->set('thesis_title', 'Sistem Presensi Berbasis QR')->set('gpa', '3.50')->set('credits_earned', '148')->set('defense_date', today()->subDays(3)->toDateString())->set('gown_size', 'M')
        ->call('save')->assertHasNoErrors()->assertRedirect();

    $registration = GraduationRegistration::where('student_id', $user->student->id)->firstOrFail();
    expect($registration->status)->toBe(GraduationStatus::Submitted);
});

it('refuses duplicate, closed-period and full-quota registrations', function () {
    $user = candidateUser();
    $payload = ['thesis_title' => 'Judul', 'gpa' => '3.5', 'credits_earned' => '148', 'defense_date' => today()->subDay()->toDateString()];

    $this->period->update(['quota' => 5]);
    expect(fn () => app(GraduationService::class)->register($user->student, $this->period->refresh(), $payload))
        ->toThrow(ValidationException::class);

    $this->period->update(['quota' => null]);
    app(GraduationService::class)->register($user->student, $this->period->refresh(), $payload);
    expect(fn () => app(GraduationService::class)->register($user->student, $this->period->refresh(), $payload))
        ->toThrow(ValidationException::class);

    $this->period->update(['registration_closes_on' => today()->subDay()]);
    $other = Student::factory()->create(['tenant_id' => $user->tenant_id]);
    expect(fn () => app(GraduationService::class)->register($other, $this->period->refresh(), $payload))
        ->toThrow(ValidationException::class);
});

it('lets an admin approve and requires a reason to reject', function () {
    $pending = GraduationRegistration::where('status', GraduationStatus::Submitted)->firstOrFail();

    Livewire::actingAs($this->admin)->test(Registrations::class, ['period' => $this->period])
        ->call('startReject', $pending->id)->call('reject')->assertHasErrors('reason')
        ->set('reason', 'Berkas sidang belum lengkap.')->call('reject')->assertHasNoErrors();

    expect($pending->refresh()->status)->toBe(GraduationStatus::Rejected)->and($pending->rejection_reason)->toBe('Berkas sidang belum lengkap.');
    $this->assertDatabaseHas('audit_logs', ['action' => 'graduation.verified']);

    $second = GraduationRegistration::where('status', GraduationStatus::Submitted)->firstOrFail();
    Livewire::actingAs($this->admin)->test(Registrations::class, ['period' => $this->period])->call('approve', $second->id);
    expect($second->refresh()->status)->toBe(GraduationStatus::Approved);
});

it('prevents non-admin staff from verifying registrations', function () {
    $pending = GraduationRegistration::where('status', GraduationStatus::Submitted)->firstOrFail();
    $principal = User::where('email', 'kaprodi@aksaraloka.test')->firstOrFail();

    Livewire::actingAs($principal)->test(Registrations::class, ['period' => $this->period])
        ->call('approve', $pending->id)->assertForbidden();
});

it('only issues the minutes when nothing is pending, then freezes the participants', function () {
    $form = fn () => Livewire::actingAs($this->admin)->test(Minutes::class, ['period' => $this->period])
        ->set('chair_name', 'Dr. Hendra Wijaya')->set('approver_name', 'Prof. Sinta Dewi');

    $form()->call('finalize')->assertHasErrors('number');
    expect($this->period->refresh()->isFinalized())->toBeFalse();

    GraduationRegistration::where('status', GraduationStatus::Submitted)->update(['status' => GraduationStatus::Approved]);

    $form()->call('finalize')->assertHasNoErrors();

    $minute = $this->period->refresh()->minute;
    expect($minute->finalized_at)->not->toBeNull()->and($minute->participants)->toHaveCount(5);

    // Keputusan dikunci setelah terbit.
    $registration = GraduationRegistration::where('status', GraduationStatus::Approved)->firstOrFail();
    expect(fn () => app(GraduationService::class)->decide($registration->load('period.minute'), $this->admin, false, 'Terlambat'))
        ->toThrow(ValidationException::class);

    // Perubahan data setelahnya tidak memengaruhi arsip.
    Student::query()->whereKey($registration->student_id)->update(['name' => 'Nama Diubah']);
    expect(collect($minute->refresh()->participants)->pluck('name')->contains('Nama Diubah'))->toBeFalse();
});

it('serves the printable minutes only after they are issued', function () {
    $this->actingAs($this->admin)->get(route('graduation.minutes.print', $this->period))->assertNotFound();

    GraduationRegistration::where('status', GraduationStatus::Submitted)->update(['status' => GraduationStatus::Approved]);
    Livewire::actingAs($this->admin)->test(Minutes::class, ['period' => $this->period])
        ->set('chair_name', 'Dr. Hendra Wijaya')->set('approver_name', 'Prof. Sinta Dewi')->call('finalize');

    $this->actingAs(User::where('email', 'kaprodi@aksaraloka.test')->firstOrFail())
        ->get(route('graduation.minutes.print', $this->period))
        ->assertOk()->assertSee('Berita Acara Pendaftaran Wisuda', false)->assertSee('Anindya Putri Lestari')->assertSee('Dr. Hendra Wijaya');

    $this->actingAs(User::where('email', 'mahasiswa@aksaraloka.test')->firstOrFail())
        ->get(route('graduation.minutes.print', $this->period))->assertForbidden();
});

it('creates a period with validated dates', function () {
    Livewire::actingAs($this->admin)->test(Periods::class)
        ->set('name', 'Wisuda Periode I 2027')->set('registration_opens_on', '2027-01-10')->set('registration_closes_on', '2027-01-05')->set('ceremony_on', '2027-03-01')
        ->call('save')->assertHasErrors('registration_closes_on')
        ->set('registration_closes_on', '2027-02-01')->call('save')->assertHasNoErrors();

    expect(GraduationPeriod::where('name', 'Wisuda Periode I 2027')->exists())->toBeTrue();
});
