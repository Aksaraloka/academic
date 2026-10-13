<?php

namespace App\Livewire\Wisuda;

use App\Models\GraduationNews;
use App\Models\GraduationPeriod;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Kelola berita wisuda')]
class NewsManage extends Component
{
    use WithFileUploads;

    public ?int $editingId = null;

    public string $title = '';

    public string $summary = '';

    public string $body = '';

    public string $graduation_period_id = '';

    public bool $is_published = true;

    public $poster;

    public function edit(int $newsId): void
    {
        $news = GraduationNews::query()->findOrFail($newsId);

        $this->fill([
            'editingId' => $news->id,
            'title' => $news->title,
            'summary' => $news->summary,
            'body' => (string) $news->body,
            'graduation_period_id' => (string) $news->graduation_period_id,
            'is_published' => $news->is_published,
        ]);
        $this->poster = null;
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'title', 'summary', 'body', 'graduation_period_id', 'poster']);
        $this->is_published = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:200'],
            'summary' => ['required', 'string', 'max:300'],
            'body' => ['nullable', 'string', 'max:10000'],
            'graduation_period_id' => ['nullable', 'exists:graduation_periods,id'],
            'is_published' => ['boolean'],
            'poster' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ], [], [
            'title' => 'judul', 'summary' => 'ringkasan', 'body' => 'isi berita',
            'graduation_period_id' => 'periode', 'poster' => 'poster',
        ]);

        $news = $this->editingId ? GraduationNews::query()->findOrFail($this->editingId) : new GraduationNews(['created_by' => auth()->id()]);

        $news->fill([
            'title' => $data['title'],
            'summary' => $data['summary'],
            'body' => $data['body'] ?: null,
            'graduation_period_id' => $data['graduation_period_id'] ?: null,
            'is_published' => $data['is_published'],
            'published_at' => $data['is_published'] ? ($news->published_at ?? now()) : null,
        ]);

        if ($this->poster) {
            $this->deletePoster($news);
            $news->poster_path = $this->poster->store('wisuda-posters', 'local');
        }

        $news->save();

        $this->cancel();
        $this->dispatch('toast', message: 'Berita disimpan.', type: 'success');
    }

    public function togglePublished(int $newsId): void
    {
        $news = GraduationNews::query()->findOrFail($newsId);
        $publish = ! $news->is_published;

        $news->update(['is_published' => $publish, 'published_at' => $publish ? ($news->published_at ?? now()) : null]);
    }

    public function delete(int $newsId): void
    {
        $news = GraduationNews::query()->findOrFail($newsId);
        $this->deletePoster($news);
        $news->delete();

        if ($this->editingId === $newsId) {
            $this->cancel();
        }

        $this->dispatch('toast', message: 'Berita dihapus.', type: 'success');
    }

    private function deletePoster(GraduationNews $news): void
    {
        if ($news->poster_path) {
            Storage::disk('local')->delete($news->poster_path);
            $news->poster_path = null;
        }
    }

    public function render()
    {
        return view('livewire.wisuda.news-manage', [
            'items' => GraduationNews::query()->with('period')->orderByDesc('created_at')->get(),
            'periods' => GraduationPeriod::query()->orderByDesc('ceremony_on')->get(),
        ]);
    }
}
