<div class="space-y-6">
    <x-page-header title="Kelola berita" subtitle="Unggah poster dan pengumuman yang tampil di beranda.">
        <x-slot:actions>
            <x-button :href="route('dashboard')" wire:navigate variant="secondary" icon="arrow-left">Beranda</x-button>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="save">
        <x-card :title="$editingId ? 'Ubah berita' : 'Berita baru'">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-field label="Judul" name="title" required>
                        <input id="title" type="text" wire:model="title" class="input" @error('title') aria-invalid="true" @enderror>
                    </x-field>
                </div>
                <x-field label="Kategori" name="category" required>
                    <select id="category" wire:model.live="category" class="input">
                        @foreach ($categories as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach
                    </select>
                </x-field>
                @if ($category === 'wisuda')
                    <x-field label="Periode wisuda" name="graduation_period_id" hint="Opsional. Mahasiswa mendapat tombol daftar bila periode dibuka.">
                        <select id="graduation_period_id" wire:model="graduation_period_id" class="input">
                            <option value="">Tanpa periode</option>
                            @foreach ($periods as $period)<option value="{{ $period->id }}">{{ $period->name }}</option>@endforeach
                        </select>
                    </x-field>
                @endif
                <div class="sm:col-span-2">
                    <x-field label="Ringkasan" name="summary" required hint="Tampil di kartu poster (maks. 300 karakter).">
                        <textarea id="summary" wire:model="summary" rows="2" class="input" @error('summary') aria-invalid="true" @enderror></textarea>
                    </x-field>
                </div>
                <div class="sm:col-span-2">
                    <x-field label="Isi berita" name="body" hint="Pisahkan paragraf dengan satu baris kosong.">
                        <textarea id="body" wire:model="body" rows="5" class="input"></textarea>
                    </x-field>
                </div>
                <div class="sm:col-span-2">
                    <x-field label="Poster" name="poster" hint="JPG, PNG, atau WebP, maksimal 3 MB. Lanskap 16:9 (mis. 1280×720) paling pas.">
                        <input id="poster" type="file" wire:model="poster" accept="image/jpeg,image/png,image/webp" class="input">
                    </x-field>
                </div>
                @if ($poster && $poster->isPreviewable())
                    <div class="sm:col-span-2">
                        <img src="{{ $poster->temporaryUrl() }}" alt="Pratinjau poster" class="max-h-64 rounded-lg border border-border">
                    </div>
                @endif
                <label class="flex min-h-11 items-center gap-2 text-sm sm:col-span-2">
                    <input type="checkbox" wire:model="is_published" class="size-5 accent-primary">
                    Terbitkan (terlihat oleh seluruh sivitas akademika)
                </label>
                <label class="flex min-h-11 items-center gap-2 text-sm sm:col-span-2">
                    <input type="checkbox" wire:model="is_featured" class="size-5 accent-primary">
                    Tampilkan di banner utama beranda (maksimal 5 berita terbaru)
                </label>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <x-button type="submit" wire:loading.attr="disabled" wire:target="save,poster">Simpan perubahan</x-button>
                @if ($editingId)<x-button variant="secondary" wire:click="cancel">Batal</x-button>@endif
            </div>
        </x-card>
    </form>

    <x-card :padding="false">
        @forelse ($items as $item)
            <div class="flex min-h-14 flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0" wire:key="item-{{ $item->id }}">
                <div class="size-12 shrink-0 overflow-hidden rounded-md bg-brand">
                    @if ($item->hasPoster())<img src="{{ route('news.poster', $item) }}" alt="" class="size-full object-cover" loading="lazy">@endif
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ $item->title }}</p>
                    <p class="truncate text-xs text-text-muted">{{ $item->category->label() }} · {{ $item->created_at->translatedFormat('j M Y') }}</p>
                </div>
                <x-badge :variant="$item->is_published ? 'success' : 'warning'">{{ $item->is_published ? 'Terbit' : 'Draf' }}</x-badge>
                <div class="flex gap-2">
                    <x-button size="sm" variant="secondary" wire:click="togglePublished({{ $item->id }})">{{ $item->is_published ? 'Tarik' : 'Terbitkan' }}</x-button>
                    <x-button size="sm" variant="secondary" wire:click="edit({{ $item->id }})">Ubah</x-button>
                    <x-button size="sm" variant="ghost" wire:click="delete({{ $item->id }})" wire:confirm="Hapus berita ini beserta posternya?">
                        <x-icon name="trash" class="size-4 text-danger" /><span class="sr-only">Hapus {{ $item->title }}</span>
                    </x-button>
                </div>
            </div>
        @empty
            <x-empty-state icon="megaphone" title="Belum ada berita" description="Isi formulir di atas untuk membuat berita pertama." />
        @endforelse
    </x-card>
</div>
