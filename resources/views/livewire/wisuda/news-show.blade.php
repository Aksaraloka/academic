<article class="mx-auto max-w-3xl space-y-6">
    <div>
        <x-button :href="route('wisuda.home')" wire:navigate variant="ghost" size="sm" icon="arrow-left">Beranda wisuda</x-button>
    </div>

    <header class="space-y-2">
        <div class="flex flex-wrap items-center gap-2">
            <p class="text-sm text-text-muted">{{ ($news->published_at ?? $news->created_at)->translatedFormat('l, j F Y') }}</p>
            @unless ($news->is_published)<x-badge variant="warning" icon="eye-slash">Draf</x-badge>@endunless
            @if ($news->period)<x-badge variant="primary">{{ $news->period->name }}</x-badge>@endif
        </div>
        <h1 class="text-2xl font-bold sm:text-3xl">{{ $news->title }}</h1>
        <p class="text-base text-text-muted">{{ $news->summary }}</p>
    </header>

    @if ($news->hasPoster())
        <img src="{{ route('wisuda.news.poster', $news) }}" alt="Poster: {{ $news->title }}" class="mx-auto max-h-[80vh] w-auto max-w-full rounded-card border border-border">
    @endif

    @if ($news->body)
        <div class="space-y-4 text-base leading-relaxed">
            @foreach (preg_split('/\R{2,}/', trim($news->body)) as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>
    @endif

    @if ($news->period && auth()->user()->hasRole(\App\Enums\Role::Student) && $news->period->isOpen())
        <x-button :href="route('wisuda.overview', ['periode' => $news->period->id])" wire:navigate icon="academic-cap">Daftar wisuda</x-button>
    @endif
</article>
