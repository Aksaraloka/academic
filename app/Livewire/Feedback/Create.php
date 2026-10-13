<?php

namespace App\Livewire\Feedback;

use App\Enums\FeedbackCategory;
use App\Enums\FeedbackType;
use App\Services\FeedbackService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Kirim masukan')]
class Create extends Component
{
    use WithFileUploads;

    public string $type = 'masukan';

    public string $category = 'akademik';

    public string $subject = '';

    public string $body = '';

    public bool $is_anonymous = false;

    public $attachment;

    public function save(FeedbackService $service): mixed
    {
        $data = $this->validate([
            'type' => ['required', Rule::enum(FeedbackType::class)],
            'category' => ['required', Rule::enum(FeedbackCategory::class)],
            'subject' => ['required', 'string', 'min:5', 'max:150'],
            'body' => ['required', 'string', 'min:20', 'max:5000'],
            'is_anonymous' => ['boolean'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:3072'],
        ], [], [
            'type' => 'jenis', 'category' => 'kategori', 'subject' => 'judul', 'body' => 'isi pesan', 'attachment' => 'lampiran',
        ]);

        $key = 'feedback:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'body' => 'Kamu sudah mengirim beberapa pesan. Coba lagi dalam '.ceil(RateLimiter::availableIn($key) / 60).' menit.',
            ]);
        }

        RateLimiter::hit($key, 3600);

        $ticket = $service->submit(auth()->user(), $data, $this->attachment);

        session()->flash('toast', "Terkirim. Nomor tiketmu {$ticket->number}.");

        return $this->redirectRoute('feedback.show', $ticket, navigate: true);
    }

    public function render()
    {
        return view('livewire.feedback.create', [
            'types' => FeedbackType::cases(),
            'categories' => FeedbackCategory::cases(),
        ]);
    }
}
