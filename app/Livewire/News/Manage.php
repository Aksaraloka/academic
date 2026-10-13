<?php

namespace App\Livewire\News;

use App\Enums\NewsCategory;
use App\Models\GraduationPeriod;
use App\Models\News;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Kelola berita')]
class Manage extends Component
{
    use WithFileUploads;

    public ?int $editingId = null;

    public string $title = '';

    public string $category = 'pengumuman';

    public string $summary = '';

    public string $body = '';

    public string $graduation_period_id = '';

    public bool $is_published = true;

    public bool $is_featured = false;

    public $poster;

    public function edit(int $newsId): void
    {
        $news = News::query()->findOrFail($newsId);

        $this->fill([
            'editingId' => $news->id,
            'title' => $news->title,
            'category' => $news->category->value,
            'summary' => $news->summary,
            'body' => (string) $news->body,
            'graduation_period_id' => (string) $news->graduation_period_id,
            'is_published' => $news->is_published,
            'is_featured' => $news->is_featured,
        ]);
        $this->poster = null;
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'title', 'summary', 'body', 'graduation_period_id', 'poster']);
        $this->category = 'pengumuman';
        $this->is_published = true;
        $this->is_featured = false;
    }

    public function save(): void
    {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', Rule::enum(NewsCategory::class)],
            'summary' => ['required', 'string', 'max:300'],
            'body' => ['nullable', 'string', 'max:10000'],
            'graduation_period_id' => ['nullable', 'exists:graduation_periods,id'],
            'is_published' => ['boolean'],
            'is_featured' => ['boolean'],
            'poster' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ], [], [
            'title' => 'judul', 'category' => 'kategori', 'summary' => 'ringkasan', 'body' => 'isi berita',
            'graduation_period_id' => 'periode wisuda', 'poster' => 'poster',
        ]);

        $news = $this->editingId ? News::query()->findOrFail($this->editingId) : new News(['created_by' => auth()->id()]);

        $news->fill([
            'title' => $data['title'],
            'category' => $data['category'],
            'summary' => $data['summary'],
            'body' => $data['body'] ?: null,
            'graduation_period_id' => $data['graduation_period_id'] ?: null,
            'is_published' => $data['is_published'],
            'is_featured' => $data['is_featured'],
            'published_at' => $data['is_published'] ? ($news->published_at ?? now()) : null,
        ]);

        if ($this->poster) {
            $this->deletePoster($news);
            $news->poster_path = $this->poster->store('news-posters', 'local');
        }

        $news->save();

        $this->cancel();
        $this->dispatch('toast', message: 'Berita disimpan.', type: 'success');
    }

    public function togglePublished(int $newsId): void
    {
        $news = News::query()->findOrFail($newsId);
        $publish = ! $news->is_published;

        $news->update(['is_published' => $publish, 'published_at' => $publish ? ($news->published_at ?? now()) : null]);
    }

    public function delete(int $newsId): void
    {
        $news = News::query()->findOrFail($newsId);
        $this->deletePoster($news);
        $news->delete();

        if ($this->editingId === $newsId) {
            $this->cancel();
        }

        $this->dispatch('toast', message: 'Berita dihapus.', type: 'success');
    }

    private function deletePoster(News $news): void
    {
        if ($news->poster_path) {
            Storage::disk('local')->delete($news->poster_path);
            $news->poster_path = null;
        }
    }

    public function render()
    {
        return view('livewire.news.manage', [
            'items' => News::query()->orderByDesc('created_at')->get(),
            'periods' => GraduationPeriod::query()->orderByDesc('ceremony_on')->get(),
            'categories' => NewsCategory::cases(),
        ]);
    }
}
