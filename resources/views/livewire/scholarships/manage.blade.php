<div class="space-y-6">
    <x-page-header title="Kelola beasiswa" subtitle="Buka program beasiswa, atur syarat dan kuota, lalu seleksi pendaftar.">
        <x-slot:actions>
            <x-button :href="route('scholarships.applications')" wire:navigate variant="secondary" icon="users">Seleksi pendaftar</x-button>
            <x-button wire:click="create" icon="plus">Beasiswa baru</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($formOpen)
        <form wire:submit="save">
            <x-card :title="$editingId ? 'Ubah beasiswa' : 'Beasiswa baru'">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Nama beasiswa" name="name" required><input id="name" type="text" wire:model="name" class="input" @error('name') aria-invalid="true" @enderror></x-field>
                    <x-field label="Penyelenggara" name="provider" required><input id="provider" type="text" wire:model="provider" class="input"></x-field>
                    <div class="sm:col-span-2"><x-field label="Deskripsi" name="description"><textarea id="description" wire:model="description" rows="3" class="input"></textarea></x-field></div>
                    <x-field label="Besaran per semester (Rp)" name="amount_per_term"><input id="amount_per_term" type="number" min="0" wire:model="amount_per_term" class="input text-right tabular-nums" inputmode="numeric"></x-field>
                    <x-field label="Kuota penerima" name="quota" hint="Kosongkan bila tanpa batas."><input id="quota" type="number" min="1" wire:model="quota" class="input text-right tabular-nums" inputmode="numeric"></x-field>
                    <x-field label="IPK minimal" name="min_gpa"><input id="min_gpa" type="text" wire:model="min_gpa" class="input text-right tabular-nums" inputmode="decimal" placeholder="3.00"></x-field>
                    <x-field label="Semester minimal" name="min_semester"><input id="min_semester" type="number" min="1" max="14" wire:model="min_semester" class="input text-right tabular-nums" inputmode="numeric"></x-field>
                    <x-field label="Pendaftaran dibuka" name="opens_on" required><input id="opens_on" type="date" wire:model="opens_on" class="input"></x-field>
                    <x-field label="Pendaftaran ditutup" name="closes_on" required><input id="closes_on" type="date" wire:model="closes_on" class="input"></x-field>
                    <div class="sm:col-span-2"><x-field label="Persyaratan lain" name="requirements"><textarea id="requirements" wire:model="requirements" rows="2" class="input"></textarea></x-field></div>
                    <label class="flex min-h-11 items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" wire:model="is_active" class="size-5 accent-primary"> Tampilkan ke mahasiswa</label>
                </div>
                <div class="mt-4 flex gap-2"><x-button type="submit" wire:loading.attr="disabled" wire:target="save">Simpan</x-button><x-button variant="ghost" wire:click="cancel">Batal</x-button></div>
            </x-card>
        </form>
    @endif

    <x-card :padding="false">
        @forelse ($scholarships as $scholarship)
            <div class="flex min-h-14 flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0" wire:key="schm-{{ $scholarship->id }}">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ $scholarship->name }}</p>
                    <p class="truncate text-xs text-text-muted">{{ $scholarship->provider }} · {{ $scholarship->opens_on->translatedFormat('j M') }} – {{ $scholarship->closes_on->translatedFormat('j M Y') }} · {{ $scholarship->applications_count }} pendaftar · {{ $scholarship->accepted_count }}{{ $scholarship->quota ? '/'.$scholarship->quota : '' }} diterima</p>
                </div>
                @if (! $scholarship->is_active)<x-badge>Disembunyikan</x-badge>@elseif ($scholarship->isOpen())<x-badge variant="success">Dibuka</x-badge>@else<x-badge variant="warning">Di luar jadwal</x-badge>@endif
                <x-button size="sm" variant="secondary" wire:click="edit({{ $scholarship->id }})">Ubah</x-button>
                <x-button size="sm" variant="ghost" wire:click="toggle({{ $scholarship->id }})">{{ $scholarship->is_active ? 'Sembunyikan' : 'Tampilkan' }}</x-button>
            </div>
        @empty
            <x-empty-state icon="heart" title="Belum ada beasiswa" description="Buat beasiswa pertama agar mahasiswa bisa mendaftar." />
        @endforelse
    </x-card>
</div>
