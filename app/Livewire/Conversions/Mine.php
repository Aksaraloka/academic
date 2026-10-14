<?php

namespace App\Livewire\Conversions;

use App\Enums\ConversionSource;
use App\Models\CreditConversion;
use App\Services\CreditConversionService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Konversi SKS')]
class Mine extends Component
{
    public string $source = 'internship';

    public string $internshipId = '';

    public string $description = '';

    /** @var array<int, string> */
    public array $subjectIds = [];

    public function mount(): void
    {
        abort_if(auth()->user()->student === null, 403);
    }

    public function submit(CreditConversionService $service): void
    {
        $data = $this->validate([
            'source' => ['required', 'in:'.implode(',', array_column(ConversionSource::cases(), 'value'))],
            'internshipId' => ['nullable', 'integer'],
            'description' => ['required', 'string', 'min:10', 'max:300'],
            'subjectIds' => ['required', 'array', 'min:1'],
            'subjectIds.*' => ['integer'],
        ], [], ['description' => 'dasar konversi', 'subjectIds' => 'mata kuliah', 'internshipId' => 'magang atau MBKM']);

        $service->propose(
            auth()->user()->student,
            ConversionSource::from($data['source']),
            filled($data['internshipId']) ? (int) $data['internshipId'] : null,
            $data['description'],
            $data['subjectIds'],
        );

        $this->reset(['internshipId', 'description', 'subjectIds']);
        $this->dispatch('toast', message: 'Pengajuan konversi dikirim. Tunggu penilaian program studi.', type: 'success');
    }

    public function cancel(int $id, CreditConversionService $service): void
    {
        $service->cancel(CreditConversion::query()->where('student_id', auth()->user()->student->id)->findOrFail($id), auth()->user());

        $this->dispatch('toast', message: 'Pengajuan dibatalkan.', type: 'success');
    }

    public function render(CreditConversionService $service)
    {
        $student = auth()->user()->student;
        $subjects = $service->availableSubjects($student);

        return view('livewire.conversions.mine', [
            'sources' => ConversionSource::cases(),
            'internships' => $service->convertibleInternships($student),
            'subjects' => $subjects,
            'selectedCredits' => (int) $subjects->whereIn('id', array_map('intval', $this->subjectIds))->sum('credits'),
            'maxCredits' => (int) $student->tenant->setting('conversion_max_credits'),
            'conversions' => CreditConversion::query()->with(['items.subject', 'internship'])->where('student_id', $student->id)->latest('id')->get(),
        ]);
    }
}
