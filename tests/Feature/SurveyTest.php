<?php

use App\Enums\Role;
use App\Livewire\Survey\Fill;
use App\Livewire\Survey\Form;
use App\Livewire\Survey\Index;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\SurveyService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('email', 'admin@aksaraloka.test')->firstOrFail();
    $this->principal = User::where('email', 'kaprodi@aksaraloka.test')->firstOrFail();
    $this->student = User::where('email', 'mahasiswa@aksaraloka.test')->firstOrFail();
    $this->lecturer = User::where('email', 'dosen@aksaraloka.test')->firstOrFail();
    $this->guardian = User::where('email', 'ortu@aksaraloka.test')->firstOrFail();
    $this->service = app(SurveyService::class);
    $this->satisfaction = Survey::where('title', 'like', 'Evaluasi Layanan Akademik%')->firstOrFail()->load('questions');
});

/**
 * Jawaban valid untuk semua pertanyaan survey kepuasan contoh.
 *
 * @return array<int, mixed>
 */
function validAnswers(Survey $survey): array
{
    return $survey->questions->mapWithKeys(fn ($question) => [
        $question->id => match ($question->type->value) {
            'rating' => '4',
            'pilihan' => $question->options[0],
            'pilihan_ganda' => [$question->options[0], $question->options[1]],
            default => 'Sudah bagus.',
        },
    ])->all();
}

it('adds a Survey group with Beranda, Kuesioner and Bantuan to every role sidebar', function () {
    foreach (['admin@', 'kaprodi@', 'akademik@', 'dosen@', 'petugas@', 'mahasiswa@', 'ortu@'] as $prefix) {
        $user = User::where('email', $prefix.'aksaraloka.test')->firstOrFail();

        $this->actingAs($user)->get('/beranda')->assertOk()
            ->assertSee('Survey')->assertSee('Kuesioner')->assertSee('Bantuan')
            ->assertSee(route('survey.home'), false)->assertSee(route('survey.index'), false)->assertSee(route('survey.help'), false);
    }
});

it('renders the three survey pages for every role', function () {
    foreach (['admin@', 'kaprodi@', 'akademik@', 'dosen@', 'petugas@', 'mahasiswa@', 'ortu@'] as $prefix) {
        $user = User::where('email', $prefix.'aksaraloka.test')->firstOrFail();

        foreach (['/survei', '/survei/kuesioner', '/survei/bantuan'] as $path) {
            $this->actingAs($user)->get($path)->assertOk();
        }
    }
});

it('lists only the surveys targeted at the viewer role', function () {
    $this->actingAs($this->student)->get('/survei/kuesioner')
        ->assertSee('Evaluasi Layanan Akademik')->assertSee('Evaluasi Dosen Semester')
        ->assertDontSee('Survey Kepuasan Dosen terhadap Sistem Akademik');

    $this->actingAs($this->lecturer)->get('/survei/kuesioner')
        ->assertSee('Survey Kepuasan Dosen terhadap Sistem Akademik')->assertDontSee('Evaluasi Layanan Akademik');

    $this->actingAs($this->guardian)->get('/survei/kuesioner')->assertSee('Belum ada kuesioner untukmu');
});

it('never exposes unpublished surveys to respondents', function () {
    $draft = Survey::where('title', 'Survey Fasilitas Kampus')->firstOrFail();

    $this->actingAs($this->student)->get(route('survey.fill', $draft))->assertNotFound();
    $this->actingAs($this->student)->get('/survei/kuesioner')->assertDontSee('Survey Fasilitas Kampus');
    $this->actingAs($this->admin)->get('/survei/kuesioner')->assertSee('Survey Fasilitas Kampus');
});

it('blocks surveys aimed at other roles', function () {
    $lecturerSurvey = Survey::where('title', 'Survey Kepuasan Dosen terhadap Sistem Akademik')->firstOrFail();

    $this->actingAs($this->student)->get(route('survey.fill', $lecturerSurvey))->assertNotFound();
});

it('saves an anonymous response without the user id and blocks double submission', function () {
    $response = $this->service->submit($this->satisfaction, $this->student, validAnswers($this->satisfaction));

    expect($response->user_id)->toBeNull()
        ->and($response->answers)->toHaveCount($this->satisfaction->questions->count())
        ->and($this->service->hasResponded($this->satisfaction, $this->student))->toBeTrue()
        ->and($this->service->canRespond($this->satisfaction, $this->student))->toBeFalse();

    expect(fn () => $this->service->submit($this->satisfaction, $this->student, validAnswers($this->satisfaction)))
        ->toThrow(ValidationException::class);

    expect(SurveyResponse::where('respondent_hash', $this->service->respondentHash($this->satisfaction, $this->student))->count())->toBe(1);
});

it('keeps the respondent user id on non-anonymous surveys', function () {
    $survey = Survey::where('title', 'Survey Kepuasan Dosen terhadap Sistem Akademik')->firstOrFail()->load('questions');

    $response = $this->service->submit($survey, $this->lecturer, validAnswers($survey));

    expect($response->user_id)->toBe($this->lecturer->id);
});

it('rejects invalid answers per question', function (string $case) {
    $answers = validAnswers($this->satisfaction);
    $rating = $this->satisfaction->questions->firstWhere('type.value', 'rating');
    $single = $this->satisfaction->questions->firstWhere('type.value', 'pilihan');
    $multiple = $this->satisfaction->questions->firstWhere('type.value', 'pilihan_ganda');

    match ($case) {
        'missing required' => $answers[$rating->id] = '',
        'rating out of range' => $answers[$rating->id] = '9',
        'rating not a number' => $answers[$rating->id] = 'bagus',
        'unknown option' => $answers[$single->id] = 'Pilihan siluman',
        'unknown multi option' => $answers[$multiple->id] = ['Aplikasi Aksaraloka', 'Siluman'],
    };

    expect(fn () => $this->service->submit($this->satisfaction, $this->student, $answers))->toThrow(ValidationException::class);
    expect($this->satisfaction->responses()->count())->toBe(9);
})->with(['missing required', 'rating out of range', 'rating not a number', 'unknown option', 'unknown multi option']);

it('allows skipping optional questions', function () {
    $answers = validAnswers($this->satisfaction);
    $optional = $this->satisfaction->questions->firstWhere('is_required', false);
    unset($answers[$optional->id]);

    expect($this->service->submit($this->satisfaction, $this->student, $answers)->answers)->toHaveCount($this->satisfaction->questions->count() - 1);
});

it('refuses answers when the survey is closed or not yet open', function () {
    $this->satisfaction->update(['closes_on' => today()->subDay()]);
    expect(fn () => $this->service->submit($this->satisfaction->refresh(), $this->student, validAnswers($this->satisfaction)))->toThrow(ValidationException::class);

    $this->satisfaction->update(['opens_on' => today()->addDay(), 'closes_on' => today()->addDays(5)]);
    expect(fn () => $this->service->submit($this->satisfaction->refresh(), $this->student, validAnswers($this->satisfaction)))->toThrow(ValidationException::class);
});

it('submits through the fill form and redirects with a confirmation', function () {
    $component = Livewire::actingAs($this->student)->test(Fill::class, ['survey' => $this->satisfaction]);

    foreach (validAnswers($this->satisfaction) as $questionId => $value) {
        $component->set("answers.{$questionId}", $value);
    }

    $component->call('submit')->assertHasNoErrors()->assertRedirect(route('survey.index'));

    $this->actingAs($this->student)->get(route('survey.fill', $this->satisfaction))->assertSee('Kamu sudah mengisi kuesioner ini');
});

it('shows validation errors on the fill form for required questions', function () {
    Livewire::actingAs($this->student)->test(Fill::class, ['survey' => $this->satisfaction])->call('submit')->assertHasErrors();
});

it('shows results to admin and principal only, with correct aggregates', function () {
    $rating = $this->satisfaction->questions->firstWhere('type.value', 'rating');
    $results = $this->service->results($this->satisfaction)->firstWhere(fn ($row) => $row['question']->id === $rating->id);

    expect($results['total'])->toBe(9)
        ->and(array_sum($results['distribution']))->toBe(9)
        ->and($results['average'])->toBeBetween(3.0, 5.0);

    $this->actingAs($this->admin)->get(route('survey.results', $this->satisfaction))->assertOk()->assertSee('Tingkat respons');
    $this->actingAs($this->principal)->get(route('survey.results', $this->satisfaction))->assertOk();
    $this->actingAs($this->student)->get(route('survey.results', $this->satisfaction))->assertForbidden();
    $this->actingAs($this->lecturer)->get(route('survey.results', $this->satisfaction))->assertForbidden();
});

it('computes the response rate against eligible users', function () {
    expect($this->service->eligibleCount($this->satisfaction))->toBe(13);
});

it('exports responses to CSV without identities for anonymous surveys', function () {
    $response = $this->actingAs($this->admin)->get(route('survey.export', $this->satisfaction));

    $csv = $response->streamedContent();
    $response->assertOk();
    expect($csv)->toContain('Waktu kirim')->not->toContain('Responden')->and(substr_count(trim($csv), "\n"))->toBe(9);

    $this->actingAs($this->student)->get(route('survey.export', $this->satisfaction))->assertForbidden();
});

it('lets an admin build a survey with validated questions', function () {
    $questions = [
        ['type' => 'rating', 'text' => 'Seberapa puas kamu?', 'options' => '', 'required' => true],
        ['type' => 'pilihan', 'text' => 'Pilih satu', 'options' => "A\nB", 'required' => true],
    ];

    Livewire::actingAs($this->admin)->test(Form::class)
        ->set('title', 'Survey Perpustakaan')->set('category', 'kepuasan')->set('audience', ['student', 'teacher'])
        ->set('opens_on', today()->toDateString())->set('closes_on', today()->addDays(7)->toDateString())->set('is_published', true)
        ->set('questions', $questions)->call('save')->assertHasNoErrors();

    $survey = Survey::where('title', 'Survey Perpustakaan')->firstOrFail();
    expect($survey->questions)->toHaveCount(2)->and($survey->questions[1]->options)->toBe(['A', 'B'])->and($survey->audience)->toBe(['student', 'teacher']);
});

it('validates survey dates, audience and choice options', function () {
    Livewire::actingAs($this->admin)->test(Form::class)
        ->set('title', 'Tidak valid')->set('audience', [])->set('opens_on', '2026-12-10')->set('closes_on', '2026-12-01')
        ->set('questions', [['type' => 'pilihan', 'text' => 'Tanpa opsi', 'options' => 'Satu saja', 'required' => true]])
        ->call('save')->assertHasErrors(['audience', 'closes_on']);

    Livewire::actingAs($this->admin)->test(Form::class)
        ->set('title', 'Opsi kurang')->set('audience', ['student'])
        ->set('questions', [['type' => 'pilihan', 'text' => 'Tanpa opsi', 'options' => 'Satu saja', 'required' => true]])
        ->call('save')->assertHasErrors('questions.0.options');
});

it('locks questions once responses exist but still allows editing metadata', function () {
    $component = Livewire::actingAs($this->admin)->test(Form::class, ['survey' => $this->satisfaction]);
    $before = $this->satisfaction->questions()->pluck('id')->all();

    $component->set('title', 'Judul Baru')->set('questions', [['type' => 'teks', 'text' => 'Upaya mengganti', 'options' => '', 'required' => false]])
        ->call('save')->assertHasNoErrors();

    $this->satisfaction->refresh();
    expect($this->satisfaction->title)->toBe('Judul Baru')->and($this->satisfaction->questions()->pluck('id')->all())->toBe($before);
});

it('restricts building surveys to admins', function () {
    foreach ([$this->principal, $this->student, $this->lecturer] as $user) {
        $this->actingAs($user)->get(route('survey.create'))->assertForbidden();
        $this->actingAs($user)->get(route('survey.edit', $this->satisfaction))->assertForbidden();
    }

    $this->actingAs($this->admin)->get(route('survey.create'))->assertOk();
    $this->actingAs($this->admin)->get(route('survey.edit', $this->satisfaction))->assertOk();
});

it('blocks deleting answered surveys and publishing ones without questions', function () {
    Livewire::actingAs($this->admin)->test(Index::class)->call('delete', $this->satisfaction->id);
    expect(Survey::find($this->satisfaction->id))->not->toBeNull();

    $empty = Survey::create([
        'tenant_id' => $this->admin->tenant_id, 'title' => 'Kosong', 'category' => 'umum', 'audience' => ['student'],
        'opens_on' => today(), 'closes_on' => today()->addDay(), 'is_published' => false,
    ]);

    Livewire::actingAs($this->admin)->test(Index::class)->call('togglePublished', $empty->id);
    expect($empty->refresh()->is_published)->toBeFalse();

    Livewire::actingAs($this->admin)->test(Index::class)->call('delete', $empty->id);
    expect(Survey::find($empty->id))->toBeNull();
});

it('keeps non-admins from managing surveys through the list', function () {
    Livewire::actingAs($this->principal)->test(Index::class)->call('togglePublished', $this->satisfaction->id)->assertForbidden();
    Livewire::actingAs($this->student)->test(Index::class)->call('delete', $this->satisfaction->id)->assertForbidden();
});

it('shows pending surveys and response monitoring on the survey home', function () {
    $this->actingAs($this->student)->get('/survei')->assertSee('Perlu kamu isi')->assertSee('Evaluasi Layanan Akademik');
    $this->actingAs($this->admin)->get('/survei')->assertSee('Tingkat respons kuesioner aktif');

    $this->service->submit($this->satisfaction, $this->student, validAnswers($this->satisfaction));
    $this->actingAs($this->student)->get('/survei')->assertSee('Sudah diisi');
});

it('lists the survey roles as selectable audience', function () {
    $this->actingAs($this->admin)->get(route('survey.create'))->assertSee(Role::Student->label())->assertSee(Role::Guardian->label());
});
