<?php

namespace App\Livewire\Conversions;

use App\Enums\ConversionStatus;
use App\Models\CreditConversion;
use App\Services\CreditConversionService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Konversi SKS mahasiswa')]
class Index extends Component
{
    #[Url(as: 'status')]
    public string $status = 'submitted';

    #[Url(as: 'search')]
    public string $search = '';

    public ?int $reviewingId = null;

    /** @var array<int, string> */
    public array $letters = [];

    public string $note = '';

    public function mount(CreditConversionService $service): void
    {
        abort_unless($service->isManager(auth()->user()), 403);
    }

    public function review(int $id): void
    {
        $conversion = CreditConversion::query()->with('items', 'internship')->findOrFail($id);
        $suggested = in_array($conversion->internship?->final_letter, CreditConversionService::LETTERS, true) ? $conversion->internship->final_letter : 'B';

        $this->reviewingId = $conversion->id;
        $this->letters = $conversion->items->mapWithKeys(fn ($item): array => [$item->id => $suggested])->all();
        $this->note = '';
        $this->resetErrorBag();
    }

    public function approve(CreditConversionService $service): void
    {
        $this->validate(['note' => ['nullable', 'string', 'max:500']], [], ['note' => 'catatan']);

        $service->approve(CreditConversion::query()->findOrFail($this->reviewingId), auth()->user(), $this->letters, $this->note);

        $this->reset(['reviewingId', 'letters', 'note']);
        $this->dispatch('toast', message: 'Konversi disetujui dan masuk ke transkrip.', type: 'success');
    }

    public function reject(CreditConversionService $service): void
    {
        $this->validate(['note' => ['required', 'string', 'min:5', 'max:500']], [], ['note' => 'alasan penolakan']);

        $service->reject(CreditConversion::query()->findOrFail($this->reviewingId), auth()->user(), $this->note);

        $this->reset(['reviewingId', 'letters', 'note']);
        $this->dispatch('toast', message: 'Pengajuan ditolak.', type: 'success');
    }

    public function render()
    {
        $keyword = trim($this->search);
        $counts = CreditConversion::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $conversions = CreditConversion::query()->with(['student.program', 'items.subject', 'internship'])
            ->when(ConversionStatus::tryFrom($this->status), fn ($query, $status) => $query->where('status', $status))
            ->when($keyword !== '', fn ($query) => $query->whereHas('student', fn ($student) => $student->where('name', 'like', "%{$keyword}%")->orWhere('nim', 'like', "%{$keyword}%")))
            ->latest('id')->limit(150)->get();

        return view('livewire.conversions.index', [
            'conversions' => $conversions,
            'counts' => $counts,
            'statuses' => ConversionStatus::cases(),
            'gradeLetters' => CreditConversionService::LETTERS,
            'reviewing' => $this->reviewingId ? $conversions->firstWhere('id', $this->reviewingId) : null,
        ]);
    }
}
