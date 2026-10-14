<?php

namespace App\Livewire\Curricula;

use App\Enums\SubjectType;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\Program;
use App\Models\Subject;
use App\Services\AuditLogger;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Kurikulum')]
class Index extends Component
{
    #[Url(as: 'program')]
    public string $programId = '';

    #[Url(as: 'curriculum')]
    public string $curriculumId = '';

    public string $name = '';

    public string $batch_from = '';

    public string $batch_to = '';

    public string $subjectId = '';

    public string $semester = '1';

    public string $type = 'required';

    public string $newCode = '';

    public string $newName = '';

    public string $newCredits = '3';

    public string $prerequisiteFor = '';

    public string $prerequisiteId = '';

    private function curriculum(): ?Curriculum
    {
        return $this->curriculumId !== '' ? Curriculum::query()->where('program_id', $this->programId)->find($this->curriculumId) : null;
    }

    public function updatedProgramId(): void
    {
        $this->curriculumId = '';
    }

    public function createCurriculum(AuditLogger $audit): void
    {
        $data = $this->validate([
            'programId' => ['required', 'exists:programs,id'],
            'name' => ['required', 'string', 'max:150'],
            'batch_from' => ['required', 'integer', 'between:1990,2100'],
            'batch_to' => ['nullable', 'integer', 'between:1990,2100', 'gte:batch_from'],
        ], [], ['programId' => 'program studi', 'name' => 'nama kurikulum', 'batch_from' => 'angkatan mulai', 'batch_to' => 'angkatan akhir']);

        $curriculum = Curriculum::create([
            'program_id' => $data['programId'], 'name' => $data['name'], 'batch_from' => $data['batch_from'],
            'batch_to' => $data['batch_to'] ?: null, 'is_active' => true,
        ]);
        $audit->log('curriculum.created', $curriculum, [], $curriculum->only(['name', 'batch_from', 'batch_to']));

        $this->curriculumId = (string) $curriculum->id;
        $this->reset(['name', 'batch_from', 'batch_to']);
        $this->dispatch('toast', message: 'Kurikulum dibuat.', type: 'success');
    }

    public function toggleActive(): void
    {
        $curriculum = $this->curriculum();
        abort_if($curriculum === null, 404);

        $curriculum->update(['is_active' => ! $curriculum->is_active]);
    }

    public function addSubject(): void
    {
        $curriculum = $this->curriculum();
        abort_if($curriculum === null, 404);

        $this->validate([
            'subjectId' => ['required', Rule::exists('subjects', 'id')],
            'semester' => ['required', 'integer', 'between:1,14'],
            'type' => ['required', Rule::enum(SubjectType::class)],
        ], [], ['subjectId' => 'mata kuliah', 'semester' => 'semester', 'type' => 'jenis']);

        if ($curriculum->items()->where('subject_id', $this->subjectId)->exists()) {
            throw ValidationException::withMessages(['subjectId' => 'Mata kuliah ini sudah ada di kurikulum.']);
        }

        $curriculum->items()->create(['subject_id' => $this->subjectId, 'semester' => $this->semester, 'type' => $this->type]);

        $this->reset('subjectId');
        $this->dispatch('toast', message: 'Mata kuliah ditambahkan ke kurikulum.', type: 'success');
    }

    public function createSubject(): void
    {
        $curriculum = $this->curriculum();
        abort_if($curriculum === null, 404);

        $data = $this->validate([
            'newCode' => ['required', 'string', 'max:20'],
            'newName' => ['required', 'string', 'max:100'],
            'newCredits' => ['required', 'integer', 'between:1,12'],
            'semester' => ['required', 'integer', 'between:1,14'],
            'type' => ['required', Rule::enum(SubjectType::class)],
        ], [], ['newCode' => 'kode', 'newName' => 'nama mata kuliah', 'newCredits' => 'SKS', 'semester' => 'semester', 'type' => 'jenis']);

        $subject = Subject::create([
            'program_id' => $curriculum->program_id, 'code' => $data['newCode'], 'name' => $data['newName'],
            'credits' => $data['newCredits'], 'semester' => $data['semester'],
        ]);
        $curriculum->items()->create(['subject_id' => $subject->id, 'semester' => $data['semester'], 'type' => $data['type']]);

        $this->reset(['newCode', 'newName']);
        $this->dispatch('toast', message: 'Mata kuliah baru dibuat dan ditambahkan.', type: 'success');
    }

    public function setType(int $itemId, string $type): void
    {
        $item = CurriculumSubject::query()->where('curriculum_id', $this->curriculumId)->findOrFail($itemId);

        $item->update(['type' => SubjectType::from($type)]);
    }

    public function removeItem(int $itemId): void
    {
        $item = CurriculumSubject::query()->where('curriculum_id', $this->curriculumId)->findOrFail($itemId);

        $item->delete();
        $this->dispatch('toast', message: 'Mata kuliah dikeluarkan dari kurikulum.', type: 'success');
    }

    public function addPrerequisite(): void
    {
        $curriculum = $this->curriculum();
        abort_if($curriculum === null, 404);

        $this->validate([
            'prerequisiteFor' => ['required', 'integer'],
            'prerequisiteId' => ['required', 'integer', 'different:prerequisiteFor'],
        ], [], ['prerequisiteFor' => 'mata kuliah', 'prerequisiteId' => 'prasyarat']);

        $ids = $curriculum->items()->pluck('subject_id');

        if (! $ids->contains((int) $this->prerequisiteFor) || ! $ids->contains((int) $this->prerequisiteId)) {
            throw ValidationException::withMessages(['prerequisiteId' => 'Kedua mata kuliah harus ada di kurikulum ini.']);
        }

        $subject = Subject::query()->findOrFail($this->prerequisiteFor);
        $subject->prerequisites()->syncWithoutDetaching([(int) $this->prerequisiteId]);

        $this->reset(['prerequisiteFor', 'prerequisiteId']);
        $this->dispatch('toast', message: 'Prasyarat ditambahkan.', type: 'success');
    }

    public function removePrerequisite(int $subjectId, int $prerequisiteId): void
    {
        Subject::query()->findOrFail($subjectId)->prerequisites()->detach($prerequisiteId);
    }

    public function render()
    {
        $curriculum = $this->curriculum();
        $items = $curriculum ? $curriculum->items()->with('subject.prerequisites')->get()->sortBy([['semester', 'asc'], fn ($a, $b) => strcmp($a->subject->name, $b->subject->name)])->values() : collect();

        return view('livewire.curricula.index', [
            'programs' => Program::query()->orderBy('name')->get(),
            'curricula' => $this->programId !== '' ? Curriculum::query()->where('program_id', $this->programId)->orderByDesc('batch_from')->get() : collect(),
            'curriculum' => $curriculum,
            'items' => $items,
            'bySemester' => $items->groupBy('semester'),
            'availableSubjects' => $curriculum
                ? Subject::query()->where('program_id', $curriculum->program_id)->whereNotIn('id', $items->pluck('subject_id'))->orderBy('name')->get()
                : collect(),
            'types' => SubjectType::cases(),
            'totals' => [
                'credits' => $items->sum(fn (CurriculumSubject $item): int => (int) $item->subject->credits),
                'required' => $items->filter(fn (CurriculumSubject $item): bool => $item->type === SubjectType::Required)->sum(fn (CurriculumSubject $item): int => (int) $item->subject->credits),
            ],
        ]);
    }
}
