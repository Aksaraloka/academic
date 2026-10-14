<?php

namespace App\Livewire\Thesis;

use App\Enums\ThesisStatus;
use App\Models\Thesis;
use App\Services\AcademicRecord;
use App\Services\ThesisService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Tugas akhir')]
class Mine extends Component
{
    public string $title = '';

    public string $abstract = '';

    public string $teacherId = '';

    public string $held_on = '';

    public string $topic = '';

    public string $summary = '';

    public string $next_steps = '';

    public function mount(): void
    {
        abort_if(auth()->user()->student === null, 403);

        $this->held_on = today()->toDateString();
    }

    public function propose(ThesisService $service): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'min:10', 'max:300'],
            'abstract' => ['nullable', 'string', 'max:3000'],
        ], [], ['title' => 'judul', 'abstract' => 'latar belakang']);

        $service->propose(auth()->user()->student, $data['title'], $data['abstract']);

        $this->reset(['title', 'abstract']);
        $this->dispatch('toast', message: 'Judul diajukan. Tunggu keputusan program studi.', type: 'success');
    }

    public function addLog(ThesisService $service): void
    {
        $data = $this->validate([
            'teacherId' => ['required', 'exists:teachers,id'],
            'held_on' => ['required', 'date', 'before_or_equal:today'],
            'topic' => ['required', 'string', 'max:200'],
            'summary' => ['required', 'string', 'min:10', 'max:3000'],
            'next_steps' => ['nullable', 'string', 'max:1000'],
        ], [], ['teacherId' => 'pembimbing', 'held_on' => 'tanggal bimbingan', 'topic' => 'topik', 'summary' => 'ringkasan', 'next_steps' => 'tindak lanjut']);

        $thesis = $service->current(auth()->user()->student);
        abort_if($thesis === null, 404);

        $service->addLog($thesis, (int) $data['teacherId'], now()->parse($data['held_on']), $data['topic'], $data['summary'], $data['next_steps']);

        $this->reset(['topic', 'summary', 'next_steps']);
        $this->dispatch('toast', message: 'Log bimbingan dikirim ke pembimbing untuk diverifikasi.', type: 'success');
    }

    public function requestDefense(ThesisService $service): void
    {
        $student = auth()->user()->student;
        $thesis = $service->current($student);
        abort_if($thesis === null, 404);

        $service->requestDefense($thesis, $student);

        $this->dispatch('toast', message: 'Pengajuan sidang dikirim. Program studi akan menjadwalkan.', type: 'success');
    }

    public function render(ThesisService $service)
    {
        $student = auth()->user()->student;
        $thesis = $service->current($student);
        $rejected = $thesis ? null : Thesis::query()->where('student_id', $student->id)->where('status', ThesisStatus::Rejected)->latest('id')->first();

        if ($thesis) {
            $thesis->load(['supervisors.teacher', 'logs.teacher', 'defenses.room', 'defenses.chair', 'defenses.examiner']);
        }

        return view('livewire.thesis.mine', [
            'student' => $student,
            'thesis' => $thesis,
            'rejected' => $rejected,
            'readiness' => $thesis && $thesis->status === ThesisStatus::Supervised ? $service->readiness($thesis) : null,
            'minCredits' => (int) $student->tenant->setting('thesis_min_credits_propose'),
            'credits' => app(AcademicRecord::class)->eligibility($student)['credits'],
        ]);
    }
}
