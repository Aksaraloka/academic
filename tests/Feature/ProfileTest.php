<?php

use App\Livewire\Profile\Edit;
use App\Livewire\Students\Form;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->user = User::where('email', 'mahasiswa@aksaraloka.test')->firstOrFail();
    $this->student = $this->user->student;
    $this->admin = User::where('email', 'admin@aksaraloka.test')->firstOrFail();
});

it('adds a Profil option to the user menu for every role', function () {
    foreach (['admin@', 'kaprodi@', 'akademik@', 'dosen@', 'petugas@', 'mahasiswa@', 'ortu@'] as $prefix) {
        $user = User::where('email', $prefix.'aksaraloka.test')->firstOrFail();

        $this->actingAs($user)->get('/beranda')->assertOk()->assertSee(route('profile.show'), false);
        $this->actingAs($user)->get('/profil')->assertOk()->assertSee($user->name);
        $this->actingAs($user)->get('/profil/ubah')->assertOk();
    }
});

it('requires login to view the profile', function () {
    $this->get('/profil')->assertRedirect('/masuk');
});

it('shows the complete student profile including academic data and parents', function () {
    $this->student->refresh();

    $this->actingAs($this->user)->get('/profil')->assertOk()
        ->assertSee('Data pribadi')->assertSee('Kontak dan alamat')->assertSee('Data akademik')->assertSee('Data orang tua / wali')
        ->assertSee($this->student->name)->assertSee($this->student->nim)
        ->assertSee('Teknik Informatika')->assertSee('Fakultas Teknik')->assertSee('TI-2024-A')->assertSee('Budi Santoso')
        ->assertSee($this->student->birth_place)->assertSee($this->student->religion)->assertSee($this->student->previous_school)
        ->assertSee('Bambang Hartono')->assertSee('Wiraswasta')->assertSee('Orang Tua Contoh')->assertSee('Guru SD')->assertSee('S1');
});

it('lists the father before the mother', function () {
    $this->actingAs($this->user)->get('/profil')->assertSeeInOrder(['Ayah', 'Bambang Hartono', 'Ibu', 'Orang Tua Contoh']);
});

it('masks the NIK on screen', function () {
    $nik = $this->student->nik;
    expect($nik)->toHaveLength(16);

    $this->actingAs($this->user)->get('/profil')->assertSee(substr($nik, 0, 4).'••••••••'.substr($nik, -4))->assertDontSee($nik);
});

it('shows a hint when parent data is missing', function () {
    $this->student->guardians()->detach();

    $this->actingAs($this->user)->get('/profil')->assertSee('Data orang tua belum diisi');
});

it('only shows the own profile and never another students data', function () {
    $other = Student::where('id', '!=', $this->student->id)->firstOrFail();

    $this->actingAs($this->user)->get('/profil')->assertDontSee($other->nim)->assertDontSee($other->maskedNik());
});

it('shows lecturer and guardian profiles', function () {
    $this->actingAs(User::where('email', 'dosen@aksaraloka.test')->firstOrFail())->get('/profil')->assertSee('Data dosen')->assertSee('Dosen tetap');

    $this->actingAs(User::where('email', 'ortu@aksaraloka.test')->firstOrFail())->get('/profil')
        ->assertSee('Anak')->assertSee($this->student->name)->assertSee($this->student->nim)->assertSee('Guru SD');
});

it('lets a student update contact details but not identity fields', function () {
    $nim = $this->student->nim;
    $name = $this->student->name;

    Livewire::actingAs($this->user)->test(Edit::class)
        ->set('phone', '081311112222')->set('address', 'Jl. Baru No. 5')->set('city', 'Cimahi')->set('province', 'Jawa Barat')->set('postal_code', '40511')
        ->call('saveContact')->assertHasNoErrors();

    $this->student->refresh();
    expect($this->student->phone)->toBe('081311112222')->and($this->student->city)->toBe('Cimahi')->and($this->student->nim)->toBe($nim)->and($this->student->name)->toBe($name)
        ->and($this->user->refresh()->phone)->toBe('081311112222');
    $this->assertDatabaseHas('audit_logs', ['action' => 'student.contact_updated']);
});

it('validates contact fields', function () {
    Livewire::actingAs($this->user)->test(Edit::class)->set('postal_code', 'ABCDE')->call('saveContact')->assertHasErrors('postal_code');
});

it('changes the password only with the correct current password', function () {
    RateLimiter::clear('password-change:'.$this->user->id);

    Livewire::actingAs($this->user)->test(Edit::class)
        ->set('current_password', 'salah')->set('password', 'kataSandiBaru1')->set('password_confirmation', 'kataSandiBaru1')
        ->call('savePassword')->assertHasErrors('current_password');

    Livewire::actingAs($this->user)->test(Edit::class)
        ->set('current_password', 'password')->set('password', 'pendek')->set('password_confirmation', 'pendek')
        ->call('savePassword')->assertHasErrors('password');

    Livewire::actingAs($this->user)->test(Edit::class)
        ->set('current_password', 'password')->set('password', 'kataSandiBaru1')->set('password_confirmation', 'beda')
        ->call('savePassword')->assertHasErrors('password');

    Livewire::actingAs($this->user)->test(Edit::class)
        ->set('current_password', 'password')->set('password', 'kataSandiBaru1')->set('password_confirmation', 'kataSandiBaru1')
        ->call('savePassword')->assertHasNoErrors();

    expect(Hash::check('kataSandiBaru1', $this->user->refresh()->password))->toBeTrue();
});

it('rate limits repeated wrong current passwords', function () {
    RateLimiter::clear('password-change:'.$this->user->id);

    foreach (range(1, 5) as $i) {
        Livewire::actingAs($this->user)->test(Edit::class)->set('current_password', 'salah')->set('password', 'kataSandiBaru1')->set('password_confirmation', 'kataSandiBaru1')->call('savePassword');
    }

    Livewire::actingAs($this->user)->test(Edit::class)
        ->set('current_password', 'password')->set('password', 'kataSandiBaru1')->set('password_confirmation', 'kataSandiBaru1')
        ->call('savePassword')->assertHasErrors('current_password');

    expect(Hash::check('password', $this->user->refresh()->password))->toBeTrue();
});

it('loads the complete data into the admin form including parents', function () {
    $component = Livewire::actingAs($this->admin)->test(Form::class, ['student' => $this->student]);

    $component->assertSet('nik', $this->student->nik)->assertSet('birth_place', $this->student->birth_place)->assertSet('previous_school', $this->student->previous_school);
    expect(collect($component->get('parents'))->pluck('name', 'relation')->all())->toMatchArray(['Ayah' => 'Bambang Hartono', 'Ibu' => 'Orang Tua Contoh']);
});

it('lets an admin save the extended student data and parents', function () {
    Livewire::actingAs($this->admin)->test(Form::class)
        ->set('name', 'Citra Lestari')->set('nim', '2499001')->set('nik', '3273012345678901')->set('birth_place', 'Bandung')->set('religion', 'Islam')
        ->set('admission_path', 'SNBT')->set('previous_school', 'SMA Negeri 3 Bandung')->set('city', 'Bandung')->set('postal_code', '40123')
        ->set('parents.0.name', 'Hendra')->set('parents.0.occupation', 'PNS')->set('parents.0.education', 'S1')
        ->set('parents.1.name', 'Sari')->set('parents.1.phone', '08123')
        ->call('addGuardian')->set('parents.2.name', 'Paman Joko')
        ->call('save')->assertHasNoErrors();

    $student = Student::where('nim', '2499001')->firstOrFail();
    expect($student->nik)->toBe('3273012345678901')->and($student->nationality)->toBe('Indonesia')->and($student->admission_path)->toBe('SNBT')
        ->and($student->guardians->pluck('name', 'relation')->all())->toBe(['Ayah' => 'Hendra', 'Ibu' => 'Sari', 'Wali' => 'Paman Joko'])
        ->and($student->guardians->firstWhere('relation', 'Ayah')->occupation)->toBe('PNS');
});

it('validates NIK length and uniqueness and religion options', function () {
    $existingNik = Student::whereNotNull('nik')->where('id', '!=', $this->student->id)->value('nik');

    Livewire::actingAs($this->admin)->test(Form::class)->set('name', 'X')->set('nim', '1')->set('nik', '123')->call('save')->assertHasErrors('nik');
    Livewire::actingAs($this->admin)->test(Form::class)->set('name', 'X')->set('nim', '1')->set('nik', $existingNik)->call('save')->assertHasErrors('nik');
    Livewire::actingAs($this->admin)->test(Form::class)->set('name', 'X')->set('nim', '1')->set('religion', 'Kejawen')->call('save')->assertHasErrors('religion');
});

it('detaches a parent without deleting the shared record', function () {
    $component = Livewire::actingAs($this->admin)->test(Form::class, ['student' => $this->student]);
    $guardianId = collect($component->get('parents'))->firstWhere('relation', 'Ayah')['id'];

    $component->set('parents.0.name', '')->call('save')->assertHasNoErrors();

    expect($this->student->guardians()->where('guardians.id', $guardianId)->exists())->toBeFalse()
        ->and(Guardian::find($guardianId))->not->toBeNull();
});

it('does not let the admin form edit guardians of other students', function () {
    $foreign = Guardian::where('name', '!=', 'Bambang Hartono')->where('relation', 'Ibu')->firstOrFail();
    $component = Livewire::actingAs($this->admin)->test(Form::class, ['student' => $this->student]);

    $component->set('parents.0.id', $foreign->id)->set('parents.0.name', 'Diubah Paksa')->call('save')->assertHasNoErrors();

    expect($foreign->refresh()->name)->not->toBe('Diubah Paksa');
});
