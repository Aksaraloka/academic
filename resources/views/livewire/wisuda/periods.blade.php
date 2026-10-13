<div class="space-y-6">
    <x-page-header title="Periode wisuda" subtitle="Atur jadwal pendaftaran dan pelaksanaan wisuda.">
        <x-slot:actions>
            <x-button :href="route('wisuda.overview')" wire:navigate variant="secondary" icon="arrow-left">Pendaftaran wisuda</x-button>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="save">
        <x-card title="Periode baru">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-field label="Nama periode" name="name" required>
                        <input id="name" type="text" wire:model="name" class="input" placeholder="Wisuda Periode II Tahun 2026" @error('name') aria-invalid="true" @enderror>
                    </x-field>
                </div>
                <x-field label="Pendaftaran dibuka" name="registration_opens_on" required><input id="registration_opens_on" type="date" wire:model="registration_opens_on" class="input"></x-field>
                <x-field label="Pendaftaran ditutup" name="registration_closes_on" required><input id="registration_closes_on" type="date" wire:model="registration_closes_on" class="input"></x-field>
                <x-field label="Tanggal wisuda" name="ceremony_on" required><input id="ceremony_on" type="date" wire:model="ceremony_on" class="input"></x-field>
                <x-field label="Kuota" name="quota" hint="Kosongkan bila tidak dibatasi."><input id="quota" type="number" min="1" wire:model="quota" class="input" inputmode="numeric"></x-field>
                <div class="sm:col-span-2">
                    <x-field label="Tempat" name="venue"><input id="venue" type="text" wire:model="venue" class="input" placeholder="Auditorium Utama"></x-field>
                </div>
                <label class="flex min-h-11 items-center gap-2 text-sm sm:col-span-2">
                    <input type="checkbox" wire:model="is_published" class="size-5 accent-primary">
                    Terbitkan sekarang (mahasiswa bisa melihat periode ini)
                </label>
            </div>
            <div class="mt-4"><x-button type="submit" wire:loading.attr="disabled" wire:target="save">Simpan perubahan</x-button></div>
        </x-card>
    </form>

    <x-card :padding="false">
        @forelse ($periods as $period)
            <div class="flex min-h-14 flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0" wire:key="per-{{ $period->id }}">
                <x-icon name="academic-cap" class="text-primary" />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ $period->name }}</p>
                    <p class="truncate text-xs text-text-muted">Wisuda {{ $period->ceremony_on->translatedFormat('j F Y') }} · {{ $period->registrations_count }} pendaftar{{ $period->quota ? ' dari kuota '.$period->quota : '' }}</p>
                </div>
                <x-badge :variant="$period->phaseVariant()">{{ $period->phaseLabel() }}</x-badge>
                @if ($period->minute?->finalized_at)<x-badge variant="success" icon="document-text">Berita acara terbit</x-badge>@endif
                <div class="flex gap-2">
                    <x-button size="sm" variant="secondary" wire:click="togglePublished({{ $period->id }})">{{ $period->is_published ? 'Sembunyikan' : 'Terbitkan' }}</x-button>
                    <x-button size="sm" variant="secondary" :href="route('wisuda.registrations', $period)" wire:navigate>Pendaftar</x-button>
                    <x-button size="sm" :href="route('wisuda.minutes', $period)" wire:navigate>Berita acara</x-button>
                </div>
            </div>
        @empty
            <x-empty-state icon="academic-cap" title="Belum ada periode wisuda" description="Isi formulir di atas untuk membuat periode pertama." />
        @endforelse
    </x-card>
</div>
