<?php

namespace App\Livewire\Letters;

use App\Enums\LetterType;
use App\Models\LetterRequest;
use App\Models\Teacher;
use App\Services\LetterService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Permohonan surat')]
class Mine extends Component
{
    public string $type = 'active_student';

    public string $purpose = '';

    public string $addressee = '';

    public string $institution = '';

    public string $period_start = '';

    public string $period_end = '';

    public string $recommenderId = '';

    public function mount(): void
    {
        abort_if(auth()->user()->student === null, 403);
    }

    public function submit(LetterService $service): void
    {
        $type = LetterType::tryFrom($this->type);
        abort_if($type === null, 422);

        $rules = ['purpose' => ['required', 'string', 'min:3', 'max:200']];
        $attributes = ['purpose' => mb_strtolower($type->purposeLabel()), 'addressee' => 'pihak tujuan', 'institution' => 'instansi tujuan', 'period_start' => 'tanggal mulai', 'period_end' => 'tanggal selesai', 'recommenderId' => 'dosen perekomendasi'];

        if ($type === LetterType::Recommendation) {
            $rules += ['addressee' => ['nullable', 'string', 'max:150'], 'recommenderId' => ['nullable', 'exists:teachers,id']];
        }

        if ($type === LetterType::ResearchPermit) {
            $rules += [
                'institution' => ['required', 'string', 'max:150'],
                'addressee' => ['required', 'string', 'max:150'],
                'period_start' => ['required', 'date'],
                'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            ];
        }

        $this->validate($rules, [], $attributes);

        $details = match ($type) {
            LetterType::Recommendation => array_filter(['addressee' => trim($this->addressee), 'recommender_id' => $this->recommenderId !== '' ? (int) $this->recommenderId : null]),
            LetterType::ResearchPermit => ['institution' => trim($this->institution), 'addressee' => trim($this->addressee), 'period_start' => $this->period_start, 'period_end' => $this->period_end],
            default => [],
        };

        $letter = $service->request(auth()->user()->student, $type, $this->purpose, $details);

        $this->reset(['purpose', 'addressee', 'institution', 'period_start', 'period_end', 'recommenderId']);
        $this->dispatch('toast', message: $letter->isIssued() ? 'Surat terbit. Kamu bisa langsung mengunduhnya.' : 'Permohonan dikirim. Tunggu persetujuan staf.', type: 'success');
    }

    public function cancel(int $id, LetterService $service): void
    {
        $letter = LetterRequest::query()->where('student_id', auth()->user()->student->id)->findOrFail($id);

        $service->cancel($letter, auth()->user());

        $this->dispatch('toast', message: 'Permohonan dibatalkan.', type: 'success');
    }

    public function render()
    {
        $student = auth()->user()->student;

        return view('livewire.letters.mine', [
            'types' => LetterType::cases(),
            'selected' => LetterType::tryFrom($this->type) ?? LetterType::ActiveStudent,
            'letters' => LetterRequest::query()->where('student_id', $student->id)->latest('id')->limit(50)->get(),
            'teachers' => Teacher::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
