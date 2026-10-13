<?php

namespace App\Livewire\Survey;

use App\Enums\Role;
use App\Enums\SurveyCategory;
use App\Enums\SurveyQuestionType;
use App\Models\Survey;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Kuesioner')]
class Form extends Component
{
    public ?Survey $survey = null;

    public string $title = '';

    public string $category = 'kepuasan';

    public string $description = '';

    /** @var array<int, string> */
    public array $audience = ['student'];

    public string $opens_on = '';

    public string $closes_on = '';

    public bool $is_anonymous = false;

    public bool $is_published = false;

    /** @var array<int, array{type: string, text: string, options: string, required: bool}> */
    public array $questions = [];

    public function mount(?Survey $survey = null): void
    {
        if ($survey?->exists) {
            $this->survey = $survey->load('questions');
            $this->fill([
                'title' => $survey->title,
                'category' => $survey->category->value,
                'description' => (string) $survey->description,
                'audience' => $survey->audience,
                'opens_on' => $survey->opens_on->toDateString(),
                'closes_on' => $survey->closes_on->toDateString(),
                'is_anonymous' => $survey->is_anonymous,
                'is_published' => $survey->is_published,
                'questions' => $survey->questions->map(fn ($question): array => [
                    'type' => $question->type->value,
                    'text' => $question->text,
                    'options' => implode("\n", $question->options ?? []),
                    'required' => $question->is_required,
                ])->all(),
            ]);

            return;
        }

        $this->survey = null;
        $this->opens_on = today()->toDateString();
        $this->closes_on = today()->addDays(14)->toDateString();
        $this->questions = [$this->blankQuestion()];
    }

    /**
     * @return array{type: string, text: string, options: string, required: bool}
     */
    private function blankQuestion(): array
    {
        return ['type' => 'rating', 'text' => '', 'options' => '', 'required' => true];
    }

    /**
     * Pertanyaan dikunci setelah ada responden agar hasil tetap sah.
     */
    public function isLocked(): bool
    {
        return $this->survey !== null && $this->survey->responses()->exists();
    }

    public function addQuestion(): void
    {
        if (! $this->isLocked()) {
            $this->questions[] = $this->blankQuestion();
        }
    }

    public function removeQuestion(int $index): void
    {
        if (! $this->isLocked() && count($this->questions) > 1) {
            unset($this->questions[$index]);
            $this->questions = array_values($this->questions);
        }
    }

    public function moveQuestion(int $index, int $direction): void
    {
        $target = $index + $direction;

        if ($this->isLocked() || ! isset($this->questions[$target])) {
            return;
        }

        [$this->questions[$index], $this->questions[$target]] = [$this->questions[$target], $this->questions[$index]];
    }

    public function save(): mixed
    {
        $rules = [
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', Rule::enum(SurveyCategory::class)],
            'description' => ['nullable', 'string', 'max:2000'],
            'audience' => ['required', 'array', 'min:1'],
            'audience.*' => ['required', Rule::enum(Role::class)],
            'opens_on' => ['required', 'date'],
            'closes_on' => ['required', 'date', 'after_or_equal:opens_on'],
            'is_anonymous' => ['boolean'],
            'is_published' => ['boolean'],
        ];

        if (! $this->isLocked()) {
            $rules += [
                'questions' => ['required', 'array', 'min:1', 'max:50'],
                'questions.*.type' => ['required', Rule::enum(SurveyQuestionType::class)],
                'questions.*.text' => ['required', 'string', 'max:255'],
                'questions.*.options' => ['nullable', 'string', 'max:2000'],
                'questions.*.required' => ['boolean'],
            ];
        }

        $data = $this->validate($rules, [], [
            'title' => 'judul', 'category' => 'kategori', 'audience' => 'sasaran responden', 'opens_on' => 'tanggal buka',
            'closes_on' => 'tanggal tutup', 'questions' => 'pertanyaan', 'questions.*.text' => 'teks pertanyaan',
        ]);

        $parsedQuestions = $this->isLocked() ? null : $this->parseQuestions();

        DB::transaction(function () use ($data, $parsedQuestions): void {
            $attributes = [
                'title' => $data['title'],
                'category' => $data['category'],
                'description' => $data['description'] ?: null,
                'audience' => array_values($data['audience']),
                'opens_on' => $data['opens_on'],
                'closes_on' => $data['closes_on'],
                'is_anonymous' => $data['is_anonymous'],
                'is_published' => $data['is_published'],
            ];

            $survey = $this->survey ?? new Survey(['created_by' => auth()->id()]);
            $survey->fill($attributes)->save();

            if ($parsedQuestions !== null) {
                $survey->questions()->delete();

                foreach ($parsedQuestions as $position => $question) {
                    $survey->questions()->create($question + ['position' => $position]);
                }
            }
        });

        session()->flash('toast', 'Kuesioner disimpan.');

        return $this->redirectRoute('survey.index', navigate: true);
    }

    /**
     * Ubah isian form menjadi atribut pertanyaan dan pastikan pilihan ganda punya minimal dua opsi unik.
     *
     * @return array<int, array{type: string, text: string, options: ?array<int, string>, is_required: bool}>
     */
    private function parseQuestions(): array
    {
        $errors = [];
        $parsed = [];

        foreach ($this->questions as $index => $question) {
            $type = SurveyQuestionType::from($question['type']);
            $options = null;

            if ($type->hasOptions()) {
                $options = collect(preg_split('/\R/', (string) $question['options']))
                    ->map(fn ($line) => trim((string) $line))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if (count($options) < 2) {
                    $errors["questions.{$index}.options"] = 'Isi minimal dua pilihan, satu per baris.';
                }
            }

            $parsed[] = ['type' => $type->value, 'text' => trim($question['text']), 'options' => $options, 'is_required' => (bool) $question['required']];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $parsed;
    }

    public function render()
    {
        return view('livewire.survey.form', [
            'categories' => SurveyCategory::cases(),
            'types' => SurveyQuestionType::cases(),
            'roles' => Role::cases(),
            'locked' => $this->isLocked(),
        ]);
    }
}
