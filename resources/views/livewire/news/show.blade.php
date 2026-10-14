<article class="mx-auto max-w-3xl space-y-6">
    <div>
        <x-button :href="route('dashboard')" wire:navigate variant="ghost" size="sm" icon="arrow-left">Beranda</x-button>
    </div>

    <header class="space-y-2">
        <div class="flex flex-wrap items-center gap-2">
            <x-badge :variant="$news->category->variant()">{{ $news->category->label() }}</x-badge>
            <p class="text-sm text-text-muted">{{ $news->publishedDate()->translatedFormat('l, j F Y') }}</p>
            @unless ($news->is_published)<x-badge variant="warning" icon="eye-slash">Draf</x-badge>@endunless
        </div>
        <h1 class="text-2xl font-bold sm:text-3xl">{{ $news->title }}</h1>
        <p class="text-base text-text-muted">{{ $news->summary }}</p>
    </header>

    @if ($news->hasPoster())
        <img src="{{ route('news.poster', $news) }}" alt="Poster: {{ $news->title }}" class="mx-auto max-h-[80vh] w-auto max-w-full rounded-card border border-border">
    @endif

    @if ($news->body)
        <div class="space-y-4 text-base leading-relaxed">
            @foreach (preg_split('/\R{2,}/', trim($news->body)) as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>
    @endif

    @if ($news->period && auth()->user()->hasRole(\App\Enums\Role::Student) && $news->period->isOpen())
        <x-button :href="route('graduation.overview', ['period' => $news->period->id])" wire:navigate icon="academic-cap">Daftar wisuda</x-button>
    @endif

    @if ($related->isNotEmpty())
        <section aria-labelledby="terkait" class="space-y-3 border-t border-border pt-6">
            <h2 id="terkait" class="text-lg font-semibold">Berita {{ strtolower($news->category->label()) }} lainnya</h2>
            <ul class="divide-y divide-border rounded-card border border-border bg-surface">
                @foreach ($related as $item)
                    <li wire:key="rel-{{ $item->id }}">
                        <a href="{{ route('news.show', $item) }}" wire:navigate class="flex min-h-14 items-center gap-3 px-4 py-2.5 hover:bg-surface-muted">
                            <x-icon name="megaphone" class="text-primary" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ $item->title }}</span>
                                <span class="block truncate text-xs text-text-muted">{{ $item->publishedDate()->translatedFormat('j F Y') }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</article>
