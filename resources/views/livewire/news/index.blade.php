<div class="space-y-6">
    @if ($banner->isNotEmpty())
        <section aria-roledescription="carousel" aria-label="Berita utama"
            x-data="{
                active: 0,
                count: {{ $banner->count() }},
                paused: false,
                timer: null,
                init() {
                    if (this.count < 2 || matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
                    this.timer = setInterval(() => { if (!this.paused) { this.next(); } }, 6000);
                },
                destroy() { clearInterval(this.timer); },
                next() { this.active = (this.active + 1) % this.count; },
                prev() { this.active = (this.active - 1 + this.count) % this.count; },
            }"
            @mouseenter="paused = true" @mouseleave="paused = false" @focusin="paused = true" @focusout="paused = false"
            class="relative overflow-hidden rounded-card border border-border bg-brand">
            <div class="relative aspect-[16/10] sm:aspect-[16/8] lg:aspect-[21/8]">
                @foreach ($banner as $slide)
                    <a href="{{ route('news.show', $slide) }}" wire:navigate wire:key="slide-{{ $slide->id }}"
                       role="group" aria-roledescription="slide" aria-label="{{ $loop->iteration }} dari {{ $banner->count() }}"
                       x-show="active === {{ $loop->index }}" @if (! $loop->first) x-cloak @endif
                       x-transition.opacity.duration.400ms
                       class="absolute inset-0 block">
                        @if ($slide->hasPoster())
                            <img src="{{ route('news.poster', $slide) }}" alt="" @if (! $loop->first) loading="lazy" @endif decoding="async" class="size-full object-cover">
                        @else
                            <div class="size-full bg-brand"></div>
                        @endif
                        <div class="absolute inset-x-0 bottom-0 bg-linear-to-t from-black/80 via-black/50 to-transparent px-4 pb-10 pt-12 text-white sm:px-6 sm:pb-12">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-white px-2.5 py-0.5 text-xs font-semibold text-black">{{ $slide->category->label() }}</span>
                                <span class="text-xs font-medium tabular-nums opacity-90">{{ $slide->publishedDate()->translatedFormat('j F Y') }}</span>
                            </div>
                            <h2 class="mt-2 line-clamp-2 text-lg font-bold sm:text-2xl">{{ $slide->title }}</h2>
                        </div>
                    </a>
                @endforeach
            </div>

            @if ($banner->count() > 1)
                <button type="button" @click="prev()" class="absolute left-2 top-1/2 inline-flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-black/50 text-white hover:bg-black/70">
                    <x-icon name="arrow-left" /><span class="sr-only">Berita sebelumnya</span>
                </button>
                <button type="button" @click="next()" class="absolute right-2 top-1/2 inline-flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-black/50 text-white hover:bg-black/70">
                    <x-icon name="arrow-left" class="rotate-180" /><span class="sr-only">Berita berikutnya</span>
                </button>
                <div class="absolute inset-x-0 bottom-1 flex justify-center">
                    @foreach ($banner as $slide)
                        <button type="button" @click="active = {{ $loop->index }}" wire:key="dot-{{ $slide->id }}" class="inline-flex size-8 items-center justify-center" :aria-current="active === {{ $loop->index }}">
                            <span class="size-2.5 rounded-full border border-white transition" :class="active === {{ $loop->index }} ? 'bg-white' : 'bg-white/30'"></span>
                            <span class="sr-only">Slide {{ $loop->iteration }}</span>
                        </button>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    <div class="space-y-3">
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative min-w-0 flex-1">
                <label for="cari-berita" class="sr-only">Cari berita</label>
                <x-icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-text-muted" />
                <input id="cari-berita" type="search" wire:model.live.debounce.300ms="search" class="input pl-10" placeholder="Cari berita, beasiswa, atau program">
            </div>
            @if ($isAdmin)
                <x-button :href="route('news.manage')" wire:navigate icon="pencil-square" variant="secondary">Kelola berita</x-button>
            @endif
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
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($news as $item)
                    <article wire:key="news-{{ $item->id }}" class="flex flex-col overflow-hidden rounded-card border border-border bg-surface transition hover:border-primary">
                        <a href="{{ route('news.show', $item) }}" wire:navigate class="relative block aspect-video overflow-hidden bg-brand" tabindex="-1" aria-hidden="true">
                            @if ($item->hasPoster())
                                <img src="{{ route('news.poster', $item) }}" alt="" loading="lazy" decoding="async" class="size-full object-cover">
                            @else
                                <span class="flex size-full items-end p-4 text-lg font-bold text-brand-fg">{{ $item->title }}</span>
                            @endif
                            <span class="absolute bottom-0 left-0 bg-black/75 px-3 py-1.5 text-xs font-semibold uppercase tabular-nums text-white">{{ $item->publishedDate()->translatedFormat('j M Y') }}</span>
                            @unless ($item->is_published)
                                <x-badge variant="warning" icon="eye-slash" class="absolute right-3 top-3">Draf</x-badge>
                            @endunless
                        </a>
                        <div class="flex flex-1 flex-col gap-2 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-primary">{{ $item->category->label() }}</p>
                            <h3 class="text-sm font-semibold uppercase leading-snug">
                                <a href="{{ route('news.show', $item) }}" wire:navigate class="line-clamp-3 hover:text-primary">{{ $item->title }}</a>
                            </h3>
                            <p class="line-clamp-2 text-sm text-text-muted">{{ $item->summary }}</p>
                            <a href="{{ route('news.show', $item) }}" wire:navigate class="mt-auto inline-flex min-h-11 items-center text-sm font-semibold text-primary hover:underline" aria-label="Baca selengkapnya: {{ $item->title }}">Baca selengkapnya</a>
                        </div>
                    </article>
                @endforeach
            </div>

            {{ $news->links() }}
        @endif
    </section>
</div>
