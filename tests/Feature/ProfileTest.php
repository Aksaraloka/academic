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
        ->assertSee('Data pribadi')->assertSee('Alamat sesuai KTP')->assertSee('Alamat domisili')->assertSee('Data akademik')->assertSee('Data orang tua / wali')
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

it('lets a student update phone and domicile but not identity or KTP address', function () {
    $nim = $this->student->nim;
    $name = $this->student->name;
    $ktp = $this->student->only(['address', 'city', 'province', 'postal_code']);

    Livewire::actingAs($this->user)->test(Edit::class)
        ->set('phone', '081311112222')->set('is_domicile_same', false)->set('residence_type', 'Kos/kontrakan')
        ->set('domicile_address', 'Jl. Baru No. 5 (kos)')->set('domicile_city', 'Cimahi')->set('domicile_province', 'Jawa Barat')->set('domicile_postal_code', '40511')
        ->call('saveContact')->assertHasNoErrors();

    $this->student->refresh();
    expect($this->student->phone)->toBe('081311112222')
        ->and($this->student->domicile_city)->toBe('Cimahi')
        ->and($this->student->is_domicile_same)->toBeFalse()
        ->and($this->student->only(['address', 'city', 'province', 'postal_code']))->toBe($ktp)
        ->and($this->student->nim)->toBe($nim)->and($this->student->name)->toBe($name)
        ->and($this->user->refresh()->phone)->toBe('081311112222');
    $this->assertDatabaseHas('audit_logs', ['action' => 'student.contact_updated']);
});

it('requires an address and city when the domicile differs from the KTP', function () {
    Livewire::actingAs($this->user)->test(Edit::class)
        ->set('is_domicile_same', false)->set('domicile_address', '')->set('domicile_city', '')
        ->call('saveContact')->assertHasErrors(['domicile_address', 'domicile_city']);

    Livewire::actingAs($this->user)->test(Edit::class)->set('domicile_postal_code', 'ABCDE')->call('saveContact')->assertHasErrors('domicile_postal_code');
});

it('clears the domicile fields when the student marks it the same as KTP', function () {
    expect($this->student->is_domicile_same)->toBeFalse();

    Livewire::actingAs($this->user)->test(Edit::class)->set('is_domicile_same', true)->call('saveContact')->assertHasNoErrors();

    $this->student->refresh();
    expect($this->student->is_domicile_same)->toBeTrue()->and($this->student->domicile_address)->toBeNull()->and($this->student->domicile_city)->toBeNull();
});

it('shows KTP and domicile addresses separately on the profile', function () {
    $this->student->refresh();

    $response = $this->actingAs($this->user)->get('/profil')->assertOk()
        ->assertSee('Alamat sesuai KTP')->assertSee('Alamat domisili')
        ->assertSee($this->student->address)->assertSee($this->student->city)
        ->assertSee($this->student->domicile_address)->assertSee('Kos/kontrakan')->assertSee('Perantau');

    expect($this->student->city)->not->toBe('Bandung')->and($this->student->domicile_city)->toBe('Bandung');
});

it('says so when the domicile is the same as the KTP', function () {
    $this->student->update(['is_domicile_same' => true]);

    $this->actingAs($this->user)->get('/profil')->assertSee('Sama dengan alamat sesuai KTP')->assertDontSee('Perantau');
});

it('detects migrants by differing cities', function () {
    $student = $this->student->refresh();

    expect($student->isMigrant())->toBeTrue();

    $student->forceFill(['domicile_city' => ' '.$student->city.' '])->save();
    expect($student->refresh()->isMigrant())->toBeFalse();

    $student->forceFill(['is_domicile_same' => true, 'domicile_city' => 'Bandung'])->save();
    expect($student->refresh()->isMigrant())->toBeFalse();
});

it('resolves the current address from domicile or KTP', function () {
    $student = $this->student->refresh();

    expect($student->currentAddress()['city'])->toBe('Bandung');

    $student->update(['is_domicile_same' => true]);
    expect($student->refresh()->currentAddress()['city'])->toBe($student->city);
});

it('flags migrants in the admin student list', function () {
    $this->actingAs($this->admin)->get('/mahasiswa')->assertOk()->assertSee('Perantau');
});

it('lets an admin save a migrant student with a separate domicile', function () {
    Livewire::actingAs($this->admin)->test(Form::class)
        ->set('name', 'Rahma Nasution')->set('nim', '2499002')->set('city', 'Medan')->set('province', 'Sumatera Utara')->set('address', 'Jl. Sisingamangaraja 1')
        ->set('is_domicile_same', false)->set('domicile_address', '')->call('save')->assertHasErrors(['domicile_address', 'domicile_city']);

    Livewire::actingAs($this->admin)->test(Form::class)
        ->set('name', 'Rahma Nasution')->set('nim', '2499002')->set('city', 'Medan')->set('province', 'Sumatera Utara')->set('address', 'Jl. Sisingamangaraja 1')
        ->set('is_domicile_same', false)->set('residence_type', 'Asrama')->set('domicile_address', 'Asrama Putri Kampus')->set('domicile_city', 'Bandung')
        ->call('save')->assertHasNoErrors();

    $student = Student::where('nim', '2499002')->firstOrFail();
    expect($student->isMigrant())->toBeTrue()->and($student->residence_type)->toBe('Asrama')->and($student->currentAddress()['address'])->toBe('Asrama Putri Kampus');
});

it('wipes stale domicile data when an admin marks it the same as KTP', function () {
    Livewire::actingAs($this->admin)->test(Form::class, ['student' => $this->student])
        ->assertSet('is_domicile_same', false)->set('is_domicile_same', true)->call('save')->assertHasNoErrors();

    $this->student->refresh();
    expect($this->student->domicile_address)->toBeNull()->and($this->student->domicile_city)->toBeNull();
});

it('rejects an unknown residence type', function () {
    Livewire::actingAs($this->admin)->test(Form::class)
        ->set('name', 'X')->set('nim', '1')->set('residence_type', 'Hotel bintang')->call('save')->assertHasErrors('residence_type');
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
