<?php

namespace App\Livewire\Internships;

use App\Enums\InternshipStatus;
use App\Models\Internship;
use App\Models\Teacher;
use App\Services\CreditConversionService;
use App\Services\InternshipService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Magang dan MBKM mahasiswa')]
class Index extends Component
{
    #[Url(as: 'status')]
    public string $status = 'submitted';

    #[Url(as: 'search')]
    public string $search = '';

    public ?int $reviewingId = null;

    public string $supervisorId = '';

    public string $note = '';

    public string $letter = 'A';

    public function mount(InternshipService $service): void
    {
        $user = auth()->user();

        abort_unless($service->isManager($user) || ($user->teacher !== null), 403);

        if (! $service->isManager($user)) {
            $this->status = 'approved';
        }
    }

    public function review(int $id): void
    {
        $internship = $this->scope()->findOrFail($id);

        $this->reviewingId = $internship->id;
        $this->supervisorId = (string) $internship->supervisor_id;
        $this->note = '';
        $this->letter = 'A';
        $this->resetErrorBag();
    }

    public function approve(InternshipService $service): void
    {
        $this->validate(['supervisorId' => ['required', 'exists:teachers,id'], 'note' => ['nullable', 'string', 'max:500']], [], ['supervisorId' => 'dosen pembimbing', 'note' => 'catatan']);

        $service->approve($this->scope()->findOrFail($this->reviewingId), auth()->user(), (int) $this->supervisorId, $this->note);

        $this->reset(['reviewingId', 'note', 'supervisorId']);
        $this->dispatch('toast', message: 'Pengajuan disetujui dan pembimbing ditetapkan.', type: 'success');
    }

    public function reject(InternshipService $service): void
    {
        $this->validate(['note' => ['required', 'string', 'min:5', 'max:500']], [], ['note' => 'alasan penolakan']);

        $service->reject($this->scope()->findOrFail($this->reviewingId), auth()->user(), $this->note);

        $this->reset(['reviewingId', 'note', 'supervisorId']);
        $this->dispatch('toast', message: 'Pengajuan ditolak.', type: 'success');
    }

    public function complete(InternshipService $service): void
    {
        $this->validate(['letter' => ['required', 'in:'.implode(',', CreditConversionService::LETTERS).',E'], 'note' => ['nullable', 'string', 'max:1000']], [], ['letter' => 'nilai akhir', 'note' => 'evaluasi']);

        $service->complete($this->scope()->findOrFail($this->reviewingId), auth()->user(), $this->letter, $this->note);

        $this->reset(['reviewingId', 'note']);
        $this->dispatch('toast', message: 'Program ditutup dan nilai akhir dicatat.', type: 'success');
    }

    /**
     * Staf melihat semua pengajuan; dosen hanya yang dibimbingnya.
     *
     * @return Builder<Internship>
     */
    private function scope()
    {
        $user = auth()->user();

        return Internship::query()->when(
            ! app(InternshipService::class)->isManager($user),
            fn ($query) => $query->where('supervisor_id', $user->teacher?->id ?? 0),
        );
    }

    public function render(InternshipService $service)
    {
        $keyword = trim($this->search);
        $manager = $service->isManager(auth()->user());
        $counts = $this->scope()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $internships = $this->scope()->with(['student.program', 'supervisor'])
            ->when(InternshipStatus::tryFrom($this->status), fn ($query, $status) => $query->where('status', $status))
            ->when($keyword !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('partner_name', 'like', "%{$keyword}%")
                ->orWhereHas('student', fn ($student) => $student->where('name', 'like', "%{$keyword}%")->orWhere('nim', 'like', "%{$keyword}%"))))
            ->latest('starts_on')->limit(150)->get();

        return view('livewire.internships.index', [
            'internships' => $internships,
            'counts' => $counts,
            'statuses' => InternshipStatus::cases(),
            'manager' => $manager,
            'teachers' => $manager ? Teacher::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'letters' => ['A', 'B', 'C', 'D', 'E'],
            'reviewing' => $this->reviewingId ? $internships->firstWhere('id', $this->reviewingId) : null,
        ]);
    }
}
