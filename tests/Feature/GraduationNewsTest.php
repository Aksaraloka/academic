<?php

use App\Livewire\Wisuda\NewsManage;
use App\Models\GraduationNews;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('email', 'admin@aksaraloka.test')->firstOrFail();
    $this->student = User::where('email', 'mahasiswa@aksaraloka.test')->firstOrFail();
});

it('shows the latest published news posters on the wisuda home', function () {
    $this->actingAs($this->student)->get('/wisuda')
        ->assertOk()
        ->assertSee('Berita terbaru')
        ->assertSee('Pendaftaran Wisuda Periode II Dibuka')
        ->assertDontSee('Jadwal Foto Wisudawan');
});

it('shows drafts to admin only, marked as draft', function () {
    $this->actingAs($this->admin)->get('/wisuda')->assertSee('Jadwal Foto Wisudawan')->assertSee('Draf');
});

it('orders news from newest to oldest', function () {
    $this->actingAs($this->student)->get('/wisuda')
        ->assertSeeInOrder(['Pendaftaran Wisuda Periode II Dibuka', 'Gladi Bersih dan Pengambilan Toga', 'Syarat dan Berkas Wisuda']);
});

it('serves posters through an access-checked route', function () {
    $published = GraduationNews::where('title', 'Pendaftaran Wisuda Periode II Dibuka')->firstOrFail();
    $draft = GraduationNews::where('title', 'Jadwal Foto Wisudawan')->firstOrFail();

    $this->get(route('wisuda.news.poster', $published))->assertRedirect('/masuk');

    $this->actingAs($this->student)->get(route('wisuda.news.poster', $published))
        ->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs($this->student)->get(route('wisuda.news.poster', $draft))->assertNotFound();
    $this->actingAs($this->admin)->get(route('wisuda.news.poster', $draft))->assertOk();
});

it('blocks lecturers and guardians from the wisuda news pages', function () {
    $news = GraduationNews::firstOrFail();

    foreach (['dosen@', 'ortu@'] as $prefix) {
        $user = User::where('email', $prefix.'aksaraloka.test')->firstOrFail();
        $this->actingAs($user)->get('/wisuda')->assertForbidden();
        $this->actingAs($user)->get(route('wisuda.news.show', $news))->assertForbidden();
    }
});

it('hides draft news details from students', function () {
    $draft = GraduationNews::where('title', 'Jadwal Foto Wisudawan')->firstOrFail();

    $this->actingAs($this->student)->get(route('wisuda.news.show', $draft))->assertNotFound();
    $this->actingAs($this->admin)->get(route('wisuda.news.show', $draft))->assertOk()->assertSee('Jadwal Foto Wisudawan');
});

it('lets an admin publish news with an uploaded poster and rejects non-images', function () {
    Livewire::actingAs($this->admin)->test(NewsManage::class)
        ->set('title', 'Pengumuman Gladi')->set('summary', 'Gladi dimulai pukul 08.00.')
        ->set('poster', UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'))
        ->call('save')->assertHasErrors('poster');

    Livewire::actingAs($this->admin)->test(NewsManage::class)
        ->set('title', 'Pengumuman Gladi')->set('summary', 'Gladi dimulai pukul 08.00.')
        ->set('poster', UploadedFile::fake()->image('poster.png', 800, 1000))
        ->call('save')->assertHasNoErrors();

    $news = GraduationNews::where('title', 'Pengumuman Gladi')->firstOrFail();
    expect($news->is_published)->toBeTrue()->and($news->published_at)->not->toBeNull();
    Storage::disk('local')->assertExists($news->poster_path);
});

it('deletes the poster file together with the news', function () {
    $news = GraduationNews::firstOrFail();
    Storage::disk('local')->assertExists($news->poster_path);

    Livewire::actingAs($this->admin)->test(NewsManage::class)->call('delete', $news->id);

    Storage::disk('local')->assertMissing($news->poster_path);
    expect(GraduationNews::find($news->id))->toBeNull();
});

it('keeps news management admin-only', function () {
    $this->actingAs($this->student)->get(route('wisuda.news.manage'))->assertForbidden();
    $this->actingAs(User::where('email', 'kaprodi@aksaraloka.test')->firstOrFail())->get(route('wisuda.news.manage'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('wisuda.news.manage'))->assertOk();
});

it('keeps the registration overview on its own page', function () {
    $this->actingAs($this->student)->get(route('wisuda.overview'))->assertOk()->assertSee('Status pendaftaranku');
    $this->actingAs($this->admin)->get(route('wisuda.overview'))->assertOk()->assertSee('Menunggu verifikasi');
});
