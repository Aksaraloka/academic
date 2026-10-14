<?php

namespace App\Livewire\Thesis;

use App\Enums\Role;
use App\Enums\ThesisStatus;
use App\Models\Thesis;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Tugas akhir mahasiswa')]
class Index extends Component
{
    #[Url(as: 'status')]
    public string $status = '';

    #[Url(as: 'search')]
    public string $search = '';

    public function render()
    {
        $user = auth()->user();
        $teacherId = $user->teacher?->id ?? 0;
        $keyword = trim($this->search);
        $isTeacher = $user->hasRole(Role::Teacher);

        $scope = Thesis::query()
            ->when($isTeacher, fn ($query) => $query->where(fn ($inner) => $inner
                ->whereHas('supervisors', fn ($sup) => $sup->where('teacher_id', $teacherId))
                ->orWhereHas('defenses', fn ($def) => $def->where('chair_id', $teacherId)->orWhere('examiner_id', $teacherId))));

        $counts = (clone $scope)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $theses = (clone $scope)->with(['student.program', 'supervisors.teacher', 'latestDefense'])
            ->when(ThesisStatus::tryFrom($this->status), fn ($query, $status) => $query->where('status', $status))
            ->when($keyword !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('title', 'like', "%{$keyword}%")
                ->orWhereHas('student', fn ($student) => $student->where('name', 'like', "%{$keyword}%")->orWhere('nim', 'like', "%{$keyword}%"))))
            ->latest('updated_at')->limit(150)->get();

        return view('livewire.thesis.index', [
            'theses' => $theses,
            'counts' => $counts,
            'statuses' => ThesisStatus::cases(),
            'isTeacher' => $isTeacher,
        ]);
    }
}
