<?php

namespace App\Livewire\News;

use App\Models\News;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Berita')]
class Show extends Component
{
    public News $news;

    public function mount(News $news): void
    {
        abort_unless(News::query()->visibleTo(auth()->user())->whereKey($news->id)->exists(), 404);

        $this->news = $news->load('period');
    }

    public function render()
    {
        $related = News::query()
            ->visibleTo(auth()->user())
            ->where('category', $this->news->category)
            ->whereKeyNot($this->news->id)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return view('livewire.news.show', ['related' => $related]);
    }
}
