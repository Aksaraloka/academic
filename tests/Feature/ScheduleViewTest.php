<?php

use App\Livewire\Schedule\Index;
use App\Livewire\Schedule\Manage;
use App\Models\AcademicTerm;
use App\Models\Schedule;
use App\Models\StudyGroup;
use App\Models\TeachingAssignment;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\ScheduleConflictDetector;
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->student = User::where('email', 'mahasiswa@aksaraloka.test')->firstOrFail();
    $this->lecturer = User::where('email', 'dosen@aksaraloka.test')->firstOrFail();
    $this->admin = User::where('email', 'admin@aksaraloka.test')->firstOrFail();
    $this->previous = AcademicTerm::where('is_active', false)->firstOrFail();
    $this->current = AcademicTerm::where('is_active', true)->firstOrFail();
});

it('renders the schedule page at full width', function () {
    $this->actingAs($this->student)->get('/jadwal')->assertOk()->assertSee('max-w-none', false)->assertDontSee('<main id="konten" class="mx-auto w-full max-w-6xl', false);
    $this->actingAs($this->student)->get('/beranda')->assertSee('max-w-6xl', false);
});

it('lists lectures from Monday to Saturday', function () {
    $component = Livewire::actingAs($this->student)->test(Index::class);

    expect($component->viewData('days')->values()->all())->toBe(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']);

    $component->assertSee('Sabtu')->assertSee('Kalkulus');
    expect($component->viewData('byDay')->has(6))->toBeTrue();
});

it('shows the Saturday column even when nothing is scheduled that day', function () {
    Schedule::query()->whereHas('timeSlot', fn ($query) => $query->where('day', 6))->delete();

    $days = Livewire::actingAs($this->student)->test(Index::class)->viewData('days');

    expect($days->has(6))->toBeTrue();
});

it('defaults to the active semester with the student class', function () {
    $component = Livewire::actingAs($this->student)->test(Index::class);

    expect($component->get('termId'))->toBe((string) $this->current->id)
        ->and($component->viewData('termIsActive'))->toBeTrue()
        ->and($component->viewData('stats')['courses'])->toBe(3)
        ->and($component->viewData('stats')['meetings'])->toBe(6);

    $component->assertSee('Algoritma dan Pemrograman')->assertSee('Basis Data')->assertDontSee('Pengantar Teknologi Informasi');
});

it('lets the user switch to a previous semester and see its archived schedule', function () {
    $component = Livewire::actingAs($this->student)->test(Index::class)->set('termId', (string) $this->previous->id);

    $component->assertSee('Pengantar Teknologi Informasi')->assertSee('Matematika Diskrit')->assertSee('Bahasa Inggris Akademik')
        ->assertDontSee('Algoritma dan Pemrograman')
        ->assertSee('arsip jadwal semester 2025/2026 Genap');

    expect($component->viewData('termIsActive'))->toBeFalse()->and($component->viewData('stats')['meetings'])->toBe(6);
});

it('lists every semester in the selector with the active one marked', function () {
    $html = $this->actingAs($this->student)->get('/jadwal')->getContent();

    expect($html)->toContain('2026/2027 · Ganjil (aktif)')->toContain('2025/2026 · Genap');
});

it('opens a given semester from the URL and falls back to the active one for unknown ids', function () {
    $this->actingAs($this->student)->get('/jadwal?semester='.$this->previous->id)->assertOk()->assertSee('Pengantar Teknologi Informasi');

    $component = Livewire::withQueryParams(['semester' => '99999'])->actingAs($this->student)->test(Index::class);
    expect($component->get('termId'))->toBe((string) $this->current->id);
});

it('resolves the student class per semester', function () {
    $component = Livewire::actingAs($this->student)->test(Index::class);
    $currentGroupId = $component->get('targetId');

    $component->set('termId', (string) $this->previous->id);
    $previousGroupId = $component->get('targetId');

    expect($previousGroupId)->not->toBe('')->and($previousGroupId)->not->toBe($currentGroupId)
        ->and(StudyGroup::find((int) $previousGroupId)->academic_term_id)->toBe($this->previous->id);
});

it('hides the manage button on archived semesters', function () {
    $component = Livewire::actingAs($this->admin)->test(Index::class);

    $component->assertSee('Atur jadwal');
    $component->set('termId', (string) $this->previous->id)->assertDontSee('Atur jadwal');
});

it('lets staff browse the groups of the chosen semester only', function () {
    $component = Livewire::actingAs($this->admin)->test(Index::class)->set('termId', (string) $this->previous->id);

    $groupIds = $component->viewData('options')->keys();
    expect($groupIds)->not->toBeEmpty()
        ->and(StudyGroup::whereIn('id', $groupIds)->pluck('academic_term_id')->unique()->all())->toBe([$this->previous->id]);
});

it('shows a lecturer own teaching schedule and lets them look at old semesters', function () {
    $component = Livewire::actingAs($this->lecturer)->test(Index::class);

    expect($component->get('view'))->toBe('teacher')->and($component->viewData('stats')['meetings'])->toBeGreaterThan(0);

    $component->set('termId', (string) $this->previous->id);
    expect($component->get('view'))->toBe('teacher')->and($component->viewData('stats')['meetings'])->toBeGreaterThan(0);
});

it('supports the room view', function () {
    $component = Livewire::actingAs($this->admin)->test(Index::class)->set('view', 'room');
    $roomId = $component->viewData('options')->keys()->first();

    $component->set('targetId', (string) $roomId);
    expect($component->viewData('stats')['meetings'])->toBeGreaterThan(0);
});

it('asks to choose something when no target is selected', function () {
    Livewire::actingAs($this->admin)->test(Index::class)->set('view', 'teacher')->assertSee('Pilih dulu yang ingin dilihat');
});

it('marks the current lecture and today column only in the active semester', function () {
    $slot = TimeSlot::where('day', now()->dayOfWeekIso)->orderBy('period_number')->first();

    if ($slot === null) {
        $this->markTestSkipped('Hari ini hari Minggu.');
    }

    $this->travelTo(now()->setTimeFromTimeString($slot->starts_at)->addMinutes(5));

    Livewire::actingAs($this->student)->test(Index::class)->assertSee('Hari ini');
    Livewire::actingAs($this->student)->test(Index::class)->set('termId', (string) $this->previous->id)->assertDontSee('Hari ini')->assertDontSee('Berlangsung');
});

it('only detects schedule conflicts within the same semester', function () {
    $previousSchedule = Schedule::query()->whereHas('assignment', fn ($query) => $query->where('academic_term_id', $this->previous->id))->firstOrFail()->load('assignment');
    $currentAssignment = TeachingAssignment::query()
        ->where('academic_term_id', $this->current->id)
        ->where('teacher_id', $previousSchedule->assignment->teacher_id)
        ->where('study_group_id', '!=', $previousSchedule->assignment->study_group_id)
        ->firstOrFail();

    $detector = app(ScheduleConflictDetector::class);

    expect($detector->conflictsFor($currentAssignment, $previousSchedule->time_slot_id, null))->toBeEmpty()
        ->and($detector->conflictsFor($previousSchedule->assignment->replicate()->forceFill(['id' => 0]), $previousSchedule->time_slot_id, null))->not->toBeEmpty();
});

it('creates the standard time slots for Monday through Saturday', function () {
    TimeSlot::query()->delete();

    Livewire::actingAs($this->admin)->test(Manage::class)->call('seedTimeSlots');

    expect(TimeSlot::query()->pluck('day')->unique()->sort()->values()->all())->toBe([1, 2, 3, 4, 5, 6]);
});

it('keeps the schedule page available to every role', function () {
    foreach (['admin@', 'kaprodi@', 'akademik@', 'dosen@', 'petugas@', 'mahasiswa@', 'ortu@'] as $prefix) {
        $this->actingAs(User::where('email', $prefix.'aksaraloka.test')->firstOrFail())->get('/jadwal')->assertOk()->assertSee('Semester');
    }
});
