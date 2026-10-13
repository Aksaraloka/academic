<div class="space-y-6">
    <x-page-header title="Beranda wisuda" subtitle="Kabar dan pengumuman terbaru seputar wisuda.">
        <x-slot:actions>
            <x-button :href="route('wisuda.overview')" wire:navigate variant="secondary" icon="academic-cap">Pendaftaran wisuda</x-button>
            @if ($isAdmin)
                <x-button :href="route('wisuda.news.manage')" wire:navigate icon="pencil-square">Kelola berita</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($period)
        <section class="overflow-hidden rounded-card bg-brand text-brand-fg" aria-label="Periode wisuda berjalan">
            <div class="relative flex flex-wrap items-center justify-between gap-4 p-4 sm:p-5">
                <span class="pointer-events-none absolute -right-10 -top-12 size-40 rounded-full bg-white/10" aria-hidden="true"></span>
                <div class="relative min-w-0">
                    <p class="text-xs font-medium opacity-80">{{ $period->phaseLabel() }}</p>
                    <h2 class="text-lg font-bold sm:text-xl">{{ $period->name }}</h2>
                    <p class="mt-1 text-sm opacity-90">
                        Pendaftaran {{ $period->registration_opens_on->translatedFormat('j M') }}–{{ $period->registration_closes_on->translatedFormat('j M Y') }}
                        · Wisuda {{ $period->ceremony_on->translatedFormat('j F Y') }}
                    </p>
                </div>
                @if ($isStudent && $period->isOpen())
                    <x-button :href="route('wisuda.overview', ['periode' => $period->id])" wire:navigate class="relative !bg-white !text-black">Daftar sekarang</x-button>
                @endif
            </div>
        </section>
    @endif

    <section aria-labelledby="berita-terbaru" class="space-y-4">
        <h2 id="berita-terbaru" class="text-lg font-semibold">Berita terbaru</h2>

        @if ($news->isEmpty())
            <x-card>
                <x-empty-state icon="megaphone" title="Belum ada berita wisuda" :description="$isAdmin ? 'Unggah poster pertama agar mahasiswa mendapat kabar terbaru.' : 'Pengumuman dan poster wisuda akan tampil di sini.'">
                    @if ($isAdmin)
                        <x-button :href="route('wisuda.news.manage')" wire:navigate icon="plus">Tambah berita</x-button>
                    @endif
                </x-empty-state>
            </x-card>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($news as $item)
                    <a href="{{ route('wisuda.news.show', $item) }}" wire:navigate wire:key="news-{{ $item->id }}"
                       class="group block overflow-hidden rounded-card border border-border bg-surface transition hover:border-primary">
                        <div class="relative aspect-[4/5] w-full overflow-hidden bg-brand">
                            @if ($item->hasPoster())
                                <img src="{{ route('wisuda.news.poster', $item) }}" alt="Poster: {{ $item->title }}" loading="lazy" decoding="async" class="size-full object-cover transition group-hover:scale-[1.02]">
                            @else
                                <div class="flex size-full items-end p-4 text-lg font-bold text-brand-fg">{{ $item->title }}</div>
                            @endif
                            @unless ($item->is_published)
                                <x-badge variant="warning" icon="eye-slash" class="absolute left-3 top-3">Draf</x-badge>
                            @endunless
                        </div>
                        <div class="p-4">
                            <p class="text-xs text-text-muted">{{ ($item->published_at ?? $item->created_at)->translatedFormat('j F Y') }}</p>
                            <h3 class="mt-1 line-clamp-2 text-base font-semibold">{{ $item->title }}</h3>
                            <p class="mt-1 line-clamp-2 text-sm text-text-muted">{{ $item->summary }}</p>
                        </div>
                    </a>
                @endforeach
            </div>

            {{ $news->links() }}
        @endif
    </section>
</div>
