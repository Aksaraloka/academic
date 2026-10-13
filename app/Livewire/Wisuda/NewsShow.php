<?php

namespace App\Livewire\Wisuda;

use App\Models\GraduationNews;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Berita wisuda')]
class NewsShow extends Component
{
    public GraduationNews $news;

    public function mount(GraduationNews $news): void
    {
        abort_unless(GraduationNews::query()->visibleTo(auth()->user())->whereKey($news->id)->exists(), 404);

        $this->news = $news->load('period');
    }

    public function render()
    {
        return view('livewire.wisuda.news-show');
    }
}
