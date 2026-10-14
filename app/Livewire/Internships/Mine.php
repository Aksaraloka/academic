<?php

namespace App\Livewire\Internships;

use App\Enums\InternshipType;
use App\Models\Internship;
use App\Services\AcademicRecord;
use App\Services\InternshipService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Magang dan MBKM')]
class Mine extends Component
{
    public string $type = 'internship';

    public string $partner_name = '';

    public string $position = '';

    public string $location = '';

    public string $field_supervisor = '';

    public string $starts_on = '';

    public string $ends_on = '';

    public string $proposed_credits = '';

    public string $description = '';

    public function mount(): void
    {
        abort_if(auth()->user()->student === null, 403);
    }

    public function submit(InternshipService $service): void
    {
        $data = $this->validate([
            'type' => ['required', 'in:'.implode(',', array_column(InternshipType::cases(), 'value'))],
            'partner_name' => ['required', 'string', 'max:150'],
            'position' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
            'field_supervisor' => ['nullable', 'string', 'max:120'],
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'proposed_credits' => ['required', 'integer', 'between:0,40'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'type' => 'jenis program', 'partner_name' => 'mitra', 'position' => 'posisi', 'location' => 'lokasi', 'field_supervisor' => 'pembimbing lapangan',
            'starts_on' => 'tanggal mulai', 'ends_on' => 'tanggal selesai', 'proposed_credits' => 'SKS yang diharapkan', 'description' => 'uraian kegiatan',
        ]);

        $service->submit(auth()->user()->student, $data);

        $this->reset(['partner_name', 'position', 'location', 'field_supervisor', 'starts_on', 'ends_on', 'proposed_credits', 'description']);
        $this->dispatch('toast', message: 'Pengajuan dikirim. Tunggu persetujuan program studi.', type: 'success');
    }

    public function cancel(int $id, InternshipService $service): void
    {
        $service->cancel(Internship::query()->where('student_id', auth()->user()->student->id)->findOrFail($id), auth()->user());

        $this->dispatch('toast', message: 'Pengajuan dibatalkan.', type: 'success');
    }

    public function render(AcademicRecord $records)
    {
        $student = auth()->user()->student;

        return view('livewire.internships.mine', [
            'types' => InternshipType::cases(),
            'internships' => Internship::query()->with('supervisor')->where('student_id', $student->id)->latest('starts_on')->get(),
            'minCredits' => (int) $student->tenant->setting('internship_min_credits'),
            'credits' => $records->eligibility($student)['credits'],
        ]);
    }
}
