<div class="space-y-6">
    <x-page-header :title="'Halo, '.explode(' ', auth()->user()->name)[0]" :subtitle="now()->translatedFormat('l, j F Y').' · Kabar terbaru dari '.(auth()->user()->tenant?->name ?? 'kampus')">
        @if ($isAdmin)
            <x-slot:actions>
                <x-button :href="route('news.manage')" wire:navigate icon="pencil-square">Kelola berita</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    <div class="space-y-3">
        <div class="relative">
            <label for="cari-berita" class="sr-only">Cari berita</label>
            <x-icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-text-muted" />
            <input id="cari-berita" type="search" wire:model.live.debounce.300ms="search" class="input pl-10" placeholder="Cari berita, beasiswa, atau program">
        </div>

        <nav aria-label="Kategori berita" class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0">
            <button type="button" wire:click="setCategory('')" @if ($category === '') aria-current="true" @endif
                class="inline-flex min-h-9 shrink-0 items-center rounded-full border px-4 text-sm font-medium transition {{ $category === '' ? 'border-primary bg-primary/15 text-primary' : 'border-border bg-surface text-text-muted hover:bg-surface-muted' }}">Semua</button>
            @foreach ($categories as $option)
                <button type="button" wire:click="setCategory('{{ $option->value }}')" wire:key="cat-{{ $option->value }}" @if ($category === $option->value) aria-current="true" @endif
                    class="inline-flex min-h-9 shrink-0 items-center rounded-full border px-4 text-sm font-medium transition {{ $category === $option->value ? 'border-primary bg-primary/15 text-primary' : 'border-border bg-surface text-text-muted hover:bg-surface-muted' }}">{{ $option->label() }}</button>
            @endforeach
        </nav>
    </div>

    <section aria-labelledby="berita-terbaru" class="space-y-4">
        <h2 id="berita-terbaru" class="text-lg font-semibold">Berita terbaru</h2>

        @if ($news->isEmpty())
            <x-card>
                <x-empty-state icon="megaphone" title="Belum ada berita"
                    :description="$filtered ? 'Tidak ada berita yang cocok. Coba kategori atau kata kunci lain.' : ($isAdmin ? 'Unggah poster pertama agar sivitas akademika mendapat kabar terbaru.' : 'Kabar terbaru dari kampus akan tampil di sini.')">
                    @if ($isAdmin && ! $filtered)
                        <x-button :href="route('news.manage')" wire:navigate icon="plus">Tambah berita</x-button>
                    @endif
                </x-empty-state>
            </x-card>
        @else
            @php $featured = $news->onFirstPage() && ! $filtered ? $news->first() : null; @endphp

            @if ($featured)
                <a href="{{ route('news.show', $featured) }}" wire:navigate wire:key="featured-{{ $featured->id }}"
                   class="group grid overflow-hidden rounded-card border border-border bg-surface transition hover:border-primary md:grid-cols-5">
                    <div class="relative aspect-[4/5] overflow-hidden bg-brand md:col-span-2 md:aspect-auto md:min-h-80">
                        @if ($featured->hasPoster())
                            <img src="{{ route('news.poster', $featured) }}" alt="Poster: {{ $featured->title }}" decoding="async" class="size-full object-cover">
                        @else
                            <div class="flex size-full items-end p-5 text-xl font-bold text-brand-fg">{{ $featured->title }}</div>
                        @endif
                    </div>
                    <div class="flex flex-col justify-center gap-3 p-5 md:col-span-3 md:p-8">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-badge :variant="$featured->category->variant()">{{ $featured->category->label() }}</x-badge>
                            @unless ($featured->is_published)<x-badge variant="warning" icon="eye-slash">Draf</x-badge>@endunless
                            <span class="text-xs text-text-muted">{{ $featured->publishedDate()->translatedFormat('j F Y') }}</span>
                        </div>
                        <h3 class="text-2xl font-bold sm:text-3xl">{{ $featured->title }}</h3>
                        <p class="text-base text-text-muted">{{ $featured->summary }}</p>
                        <span class="inline-flex items-center gap-1 text-sm font-semibold text-primary">Baca selengkapnya <x-icon name="arrow-left" class="size-4 rotate-180" /></span>
                    </div>
                </a>
            @endif

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($news as $item)
                    @continue($featured && $item->is($featured))
                    <a href="{{ route('news.show', $item) }}" wire:navigate wire:key="news-{{ $item->id }}"
                       class="group block overflow-hidden rounded-card border border-border bg-surface transition hover:border-primary">
                        <div class="relative aspect-[4/5] w-full overflow-hidden bg-brand">
                            @if ($item->hasPoster())
                                <img src="{{ route('news.poster', $item) }}" alt="Poster: {{ $item->title }}" loading="lazy" decoding="async" class="size-full object-cover transition group-hover:scale-[1.02]">
                            @else
                                <div class="flex size-full items-end p-4 text-lg font-bold text-brand-fg">{{ $item->title }}</div>
                            @endif
                            @unless ($item->is_published)
                                <x-badge variant="warning" icon="eye-slash" class="absolute left-3 top-3">Draf</x-badge>
                            @endunless
                        </div>
                        <div class="p-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-badge :variant="$item->category->variant()">{{ $item->category->label() }}</x-badge>
                                <span class="text-xs text-text-muted">{{ $item->publishedDate()->translatedFormat('j M Y') }}</span>
                            </div>
                            <h3 class="mt-2 line-clamp-2 text-base font-semibold">{{ $item->title }}</h3>
                            <p class="mt-1 line-clamp-2 text-sm text-text-muted">{{ $item->summary }}</p>
                        </div>
                    </a>
                @endforeach
            </div>

            {{ $news->links() }}
        @endif
    </section>
</div>
