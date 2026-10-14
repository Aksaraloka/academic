<?php

namespace App\Livewire\Activities;

use App\Enums\ActivityCategory;
use App\Enums\ActivityLevel;
use App\Models\StudentActivity;
use App\Services\ActivityService;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Aktivitas kemahasiswaan')]
class Mine extends Component
{
    use WithFileUploads;

    public string $category = 'organization';

    public string $title = '';

    public string $organizer = '';

    public string $role_or_result = '';

    public string $level = 'campus';

    public string $started_on = '';

    public string $ended_on = '';

    public string $description = '';

    public $evidence;

    public function mount(): void
    {
        abort_if(auth()->user()->student === null, 403);
    }

    public function save(ActivityService $service): void
    {
        $data = $this->validate([
            'category' => ['required', 'in:'.implode(',', array_column(ActivityCategory::cases(), 'value'))],
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'organizer' => ['required', 'string', 'max:150'],
            'role_or_result' => ['nullable', 'string', 'max:120'],
            'level' => ['required', 'in:'.implode(',', array_column(ActivityLevel::cases(), 'value'))],
            'started_on' => ['required', 'date', 'before_or_equal:today'],
            'ended_on' => ['nullable', 'date', 'after_or_equal:started_on'],
            'description' => ['nullable', 'string', 'max:1500'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:3072'],
        ], [], [
            'category' => 'kategori', 'title' => 'nama kegiatan', 'organizer' => 'penyelenggara', 'role_or_result' => 'jabatan atau capaian',
            'level' => 'tingkat', 'started_on' => 'tanggal mulai', 'ended_on' => 'tanggal selesai', 'description' => 'uraian', 'evidence' => 'bukti',
        ]);

        $service->submit(auth()->user()->student, $data, $this->evidence);

        $this->reset(['title', 'organizer', 'role_or_result', 'started_on', 'ended_on', 'description', 'evidence']);
        $this->dispatch('toast', message: 'Kegiatan dikirim untuk diverifikasi.', type: 'success');
    }

    public function delete(int $id, ActivityService $service): void
    {
        $service->delete(StudentActivity::query()->where('student_id', auth()->user()->student->id)->findOrFail($id), auth()->user());

        $this->dispatch('toast', message: 'Kegiatan dihapus.', type: 'success');
    }

    public function render(ActivityService $service)
    {
        $student = auth()->user()->student;

        return view('livewire.activities.mine', [
            'categories' => ActivityCategory::cases(),
            'levels' => ActivityLevel::cases(),
            'selected' => ActivityCategory::tryFrom($this->category) ?? ActivityCategory::Organization,
            'activities' => StudentActivity::query()->where('student_id', $student->id)->latest('started_on')->latest('id')->get(),
            'skpi' => $service->skpi($student),
        ]);
    }
}
