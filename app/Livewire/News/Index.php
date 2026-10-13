<?php

namespace App\Livewire\News;

use App\Enums\NewsCategory;
use App\Models\News;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Beranda')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'kategori')]
    public string $category = '';

    #[Url(as: 'cari')]
    public string $search = '';

    public function setCategory(string $category): void
    {
        $this->category = NewsCategory::tryFrom($category)?->value ?? '';
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $user = auth()->user();

        $news = News::query()
            ->visibleTo($user)
            ->when(NewsCategory::tryFrom($this->category), fn ($query, NewsCategory $category) => $query->where('category', $category))
            ->when($this->search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('title', 'like', "%{$this->search}%")
                ->orWhere('summary', 'like', "%{$this->search}%")))
            ->when($user->isAdmin(), fn ($query) => $query->orderBy('is_published'))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(9);

        return view('livewire.news.index', [
            'news' => $news,
            'categories' => NewsCategory::cases(),
            'isAdmin' => $user->isAdmin(),
            'filtered' => $this->category !== '' || $this->search !== '',
        ]);
    }
}
