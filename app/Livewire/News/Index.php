<?php

namespace App\Livewire\News;

use App\Enums\NewsCategory;
use App\Models\News;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Beranda')]
#[Layout('layouts.app', ['fullWidth' => true])]
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
        $filtered = $this->category !== '' || $this->search !== '';

        // Banner utama: berita unggulan yang sudah terbit. Disembunyikan saat pencarian atau filter aktif.
        $banner = $filtered ? collect() : News::query()
            ->published()
            ->where('is_featured', true)
            ->orderByDesc('published_at')
            ->limit(5)
            ->get();

        $news = News::query()
            ->visibleTo($user)
            ->when($banner->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $banner->pluck('id')))
            ->when(NewsCategory::tryFrom($this->category), fn ($query, NewsCategory $category) => $query->where('category', $category))
            ->when($this->search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('title', 'like', "%{$this->search}%")
                ->orWhere('summary', 'like', "%{$this->search}%")))
            ->when($user->isAdmin(), fn ($query) => $query->orderBy('is_published'))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(9);

        return view('livewire.news.index', [
            'banner' => $banner,
            'news' => $news,
            'categories' => NewsCategory::cases(),
            'isAdmin' => $user->isAdmin(),
            'filtered' => $filtered,
        ]);
    }
}
