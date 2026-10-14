<?php

use App\Enums\FeedbackStatus;
use App\Livewire\Feedback\Create;
use App\Livewire\Feedback\Index;
use App\Livewire\Feedback\Show;
use App\Models\FeedbackTicket;
use App\Models\User;
use App\Services\FeedbackService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('email', 'admin@aksaraloka.test')->firstOrFail();
    $this->principal = User::where('email', 'kaprodi@aksaraloka.test')->firstOrFail();
    $this->student = User::where('email', 'mahasiswa@aksaraloka.test')->firstOrFail();
    $this->guardian = User::where('email', 'ortu@aksaraloka.test')->firstOrFail();
    $this->lecturer = User::where('email', 'dosen@aksaraloka.test')->firstOrFail();
});

it('adds the feedback option to every role sidebar', function () {
    foreach (['admin@', 'kaprodi@', 'akademik@', 'dosen@', 'petugas@', 'mahasiswa@', 'ortu@'] as $prefix) {
        $user = User::where('email', $prefix.'aksaraloka.test')->firstOrFail();

        $this->actingAs($user)->get('/home')->assertOk()->assertSee('Masukan')->assertSee(route('feedback.index'), false);
        $this->actingAs($user)->get('/feedback')->assertOk();
    }
});

it('shows a badge with the number of new tickets to handlers only', function () {
    $new = FeedbackTicket::where('status', FeedbackStatus::New)->count();

    $this->actingAs($this->admin)->get('/home')->assertSee("{$new} baru");
    $this->actingAs($this->student)->get('/home')->assertDontSee("{$new} baru");
});

it('lets anyone submit feedback and creates a numbered ticket', function () {
    Livewire::actingAs($this->lecturer)->test(Create::class)
        ->set('type', 'masukan')->set('category', 'aplikasi')->set('subject', 'Tombol ekspor')->set('body', 'Tolong tambahkan tombol ekspor di halaman jadwal agar mudah dicetak.')
        ->call('save')->assertHasNoErrors()->assertRedirect();

    $ticket = FeedbackTicket::where('subject', 'Tombol ekspor')->firstOrFail();
    expect($ticket->number)->toBe(sprintf('MK-%d-%04d', now()->year, $ticket->id))
        ->and($ticket->status)->toBe(FeedbackStatus::New)
        ->and($ticket->user_id)->toBe($this->lecturer->id);
});

it('validates the form and stores attachments privately', function () {
    Livewire::actingAs($this->student)->test(Create::class)
        ->set('subject', 'Pdk')->set('body', 'Terlalu pendek')->call('save')->assertHasErrors(['subject', 'body']);

    Livewire::actingAs($this->student)->test(Create::class)
        ->set('subject', 'Lampiran salah jenis')->set('body', 'Isi pesan yang cukup panjang untuk lolos validasi.')
        ->set('attachment', UploadedFile::fake()->create('virus.exe', 10))->call('save')->assertHasErrors('attachment');

    Livewire::actingAs($this->student)->test(Create::class)
        ->set('subject', 'Bukti foto kerusakan')->set('body', 'Isi pesan yang cukup panjang untuk lolos validasi.')
        ->set('attachment', UploadedFile::fake()->image('bukti.png'))->call('save')->assertHasNoErrors();

    $ticket = FeedbackTicket::where('subject', 'Bukti foto kerusakan')->firstOrFail();
    Storage::disk('local')->assertExists($ticket->attachment_path);

    $this->actingAs($this->student)->get(route('feedback.attachment', $ticket))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs($this->admin)->get(route('feedback.attachment', $ticket))->assertOk();
    $this->actingAs($this->guardian)->get(route('feedback.attachment', $ticket))->assertNotFound();
});

it('limits how many messages one user can send per hour', function () {
    RateLimiter::clear('feedback:'.$this->student->id);

    foreach (range(1, 5) as $i) {
        Livewire::actingAs($this->student)->test(Create::class)
            ->set('subject', "Pesan nomor {$i}")->set('body', 'Isi pesan yang cukup panjang untuk lolos validasi.')->call('save')->assertHasNoErrors();
    }

    Livewire::actingAs($this->student)->test(Create::class)
        ->set('subject', 'Pesan keenam')->set('body', 'Isi pesan yang cukup panjang untuk lolos validasi.')->call('save')->assertHasErrors('body');
});

it('lists only own tickets for regular users and everything for handlers', function () {
    $own = FeedbackTicket::where('user_id', $this->student->id)->count();
    $all = FeedbackTicket::count();

    expect(Livewire::actingAs($this->student)->test(Index::class)->viewData('tickets')->total())->toBe($own)
        ->and(Livewire::actingAs($this->guardian)->test(Index::class)->viewData('tickets')->total())->toBe(1)
        ->and(Livewire::actingAs($this->admin)->test(Index::class)->viewData('tickets')->total())->toBe($all)
        ->and(Livewire::actingAs($this->principal)->test(Index::class)->viewData('tickets')->total())->toBe($all);
});

it('hides other people tickets behind a 404', function () {
    $studentTicket = FeedbackTicket::where('user_id', $this->student->id)->firstOrFail();

    $this->actingAs($this->guardian)->get(route('feedback.show', $studentTicket))->assertNotFound();
    $this->actingAs($this->lecturer)->get(route('feedback.show', $studentTicket))->assertNotFound();
    $this->actingAs($this->student)->get(route('feedback.show', $studentTicket))->assertOk()->assertSee($studentTicket->subject);
    $this->actingAs($this->principal)->get(route('feedback.show', $studentTicket))->assertOk();
});

it('hides the identity of anonymous senders from handlers but not from the sender', function () {
    $anonymous = FeedbackTicket::where('is_anonymous', true)->firstOrFail();

    $this->actingAs($this->admin)->get(route('feedback.show', $anonymous))->assertOk()->assertSee('dari Anonim')->assertDontSee($this->student->name);
    $this->actingAs($this->student)->get(route('feedback.show', $anonymous))->assertOk()->assertSee('dari Kamu');
    $this->actingAs($this->admin)->get('/feedback')->assertSee('Anonim');
});

it('moves a new ticket to in-progress when a handler replies', function () {
    $ticket = FeedbackTicket::where('status', FeedbackStatus::New)->where('is_anonymous', false)->firstOrFail();

    Livewire::actingAs($this->admin)->test(Show::class, ['ticket' => $ticket])
        ->set('body', 'Terima kasih, kami tindak lanjuti.')->call('reply')->assertHasNoErrors();

    $ticket->refresh();
    expect($ticket->status)->toBe(FeedbackStatus::InProgress)->and($ticket->handled_by)->toBe($this->admin->id)->and($ticket->replies)->toHaveCount(1);
});

it('reopens a resolved ticket when the sender replies', function () {
    $ticket = FeedbackTicket::where('status', FeedbackStatus::Resolved)->firstOrFail();

    Livewire::actingAs($this->guardian)->test(Show::class, ['ticket' => $ticket])
        ->set('body', 'Masih belum jelas, mohon dijelaskan lagi.')->call('reply')->assertHasNoErrors();

    expect($ticket->refresh()->status)->toBe(FeedbackStatus::InProgress)->and($ticket->resolved_at)->toBeNull();
});

it('does not accept replies on closed tickets or from outsiders', function () {
    $ticket = FeedbackTicket::where('user_id', $this->student->id)->firstOrFail();
    $ticket->update(['status' => FeedbackStatus::Closed]);

    Livewire::actingAs($this->student)->test(Show::class, ['ticket' => $ticket])
        ->set('body', 'Tolong dibuka lagi.')->call('reply')->assertHasErrors('body');

    $service = app(FeedbackService::class);
    $open = FeedbackTicket::where('status', FeedbackStatus::New)->firstOrFail();

    expect(fn () => $service->reply($open, $this->lecturer->id === $open->user_id ? $this->guardian : $this->lecturer, 'Saya orang luar.'))
        ->toThrow(ValidationException::class);
});

it('lets only handlers change the status and records it in the audit log', function () {
    $ticket = FeedbackTicket::where('status', FeedbackStatus::New)->where('is_anonymous', false)->firstOrFail();

    Livewire::actingAs($this->principal)->test(Show::class, ['ticket' => $ticket])
        ->set('status', 'selesai')->call('updateStatus')->assertHasNoErrors();

    $ticket->refresh();
    expect($ticket->status)->toBe(FeedbackStatus::Resolved)->and($ticket->resolved_at)->not->toBeNull()->and($ticket->handled_by)->toBe($this->principal->id);
    $this->assertDatabaseHas('audit_logs', ['action' => 'feedback.status_changed']);

    expect(fn () => app(FeedbackService::class)->changeStatus($ticket, $this->student, FeedbackStatus::Closed))
        ->toThrow(ValidationException::class);
});

it('filters the handler list by status and type', function () {
    $component = Livewire::actingAs($this->admin)->test(Index::class)->set('status', 'baru')->set('type', 'komplain');

    $tickets = $component->viewData('tickets');
    expect($tickets->total())->toBeGreaterThan(0)
        ->and($tickets->getCollection()->every(fn ($t) => $t->status === FeedbackStatus::New && $t->type->value === 'komplain'))->toBeTrue();
});
