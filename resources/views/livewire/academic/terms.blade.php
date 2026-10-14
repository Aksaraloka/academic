<div class="space-y-6">
    <x-page-header title="Semester akademik" subtitle="Tambah semester, aktifkan semester berjalan, dan kunci semester yang sudah selesai.">
        <x-slot:actions>
            <x-button :href="route('academic.calendar')" wire:navigate variant="secondary" icon="calendar-days">Kalender akademik</x-button>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="save">
        <x-card :title="$editingId ? 'Ubah semester' : 'Semester baru'">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-field label="Tahun ajaran" name="name" required>
                    <input id="name" type="text" wire:model="name" class="input" placeholder="2026/2027" inputmode="numeric" @error('name') aria-invalid="true" @enderror>
                </x-field>
                <x-field label="Semester" name="semester" required>
                    <select id="semester" wire:model="semester" class="input">
                        <option value="Ganjil">Ganjil</option>
                        <option value="Genap">Genap</option>
                    </select>
                </x-field>
                <x-field label="Batas input nilai" name="grading_closes_on" hint="Kosongkan bila tanpa batas.">
                    <input id="grading_closes_on" type="date" wire:model="grading_closes_on" class="input">
                </x-field>
                <x-field label="Perkuliahan mulai" name="starts_at" required><input id="starts_at" type="date" wire:model="starts_at" class="input"></x-field>
                <x-field label="Perkuliahan selesai" name="ends_at" required><input id="ends_at" type="date" wire:model="ends_at" class="input"></x-field>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <x-button type="submit" wire:loading.attr="disabled" wire:target="save">{{ $editingId ? 'Simpan perubahan' : 'Tambah semester' }}</x-button>
                @if ($editingId)<x-button type="button" variant="secondary" wire:click="cancel">Batal</x-button>@endif
            </div>
        </x-card>
    </form>

    <x-card :padding="false">
        @forelse ($terms as $term)
            <div class="flex min-h-14 flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0" wire:key="term-{{ $term->id }}">
                <x-icon name="calendar-days" class="text-primary" />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ $term->name }} · {{ $term->semester }}</p>
                    <p class="truncate text-xs text-text-muted">
                        {{ $term->starts_at->translatedFormat('j M Y') }} – {{ $term->ends_at->translatedFormat('j M Y') }}
                        · {{ $term->study_groups_count }} kelas
                        @if ($term->grading_closes_on) · nilai ditutup {{ $term->grading_closes_on->translatedFormat('j M Y') }}@endif
                    </p>
                </div>
                @if ($term->is_active)<x-badge variant="success" icon="check-circle">Aktif</x-badge>@endif
                @if ($term->is_locked)<x-badge icon="lock-closed">Arsip terkunci</x-badge>@endif
                <div class="flex flex-wrap gap-2">
                    @unless ($term->is_active)
                        <x-button size="sm" wire:click="activate({{ $term->id }})" wire:confirm="Aktifkan semester {{ $term->name }} {{ $term->semester }}? Semester aktif lainnya akan dinonaktifkan.">Aktifkan</x-button>
                    @endunless
                    <x-button size="sm" variant="secondary" wire:click="toggleLock({{ $term->id }})">{{ $term->is_locked ? 'Buka kunci' : 'Kunci' }}</x-button>
                    <x-button size="sm" variant="secondary" wire:click="edit({{ $term->id }})">Ubah</x-button>
                    <x-button size="sm" variant="secondary" :href="route('academic.calendar', ['term' => $term->id])" wire:navigate>Kalender</x-button>
                </div>
            </div>
        @empty
            <x-empty-state icon="calendar-days" title="Belum ada semester" description="Isi formulir di atas untuk membuat semester pertama." />
        @endforelse
    </x-card>
</div>
