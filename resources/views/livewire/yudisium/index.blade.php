<div class="space-y-6">
    <x-page-header title="Yudisium" subtitle="Penetapan kelulusan: syarat dicek otomatis dari transkrip, lalu predikat kelulusan ditentukan.">
        <x-slot:actions><x-button :href="route('thesis.index')" wire:navigate variant="secondary" icon="academic-cap">Tugas akhir</x-button></x-slot:actions>
    </x-page-header>

    <form wire:submit="create">
        <x-card title="Yudisium baru">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-field label="Nama" name="name" required><input id="name" type="text" wire:model="name" class="input" placeholder="Yudisium Periode Oktober 2026" @error('name') aria-invalid="true" @enderror></x-field>
                <x-field label="Tanggal yudisium" name="held_on" required><input id="held_on" type="date" wire:model="held_on" class="input"></x-field>
                <x-field label="Nomor SK" name="number"><input id="number" type="text" wire:model="number" class="input" placeholder="Opsional"></x-field>
            </div>
            <div class="mt-4"><x-button type="submit" icon="plus" wire:loading.attr="disabled" wire:target="create">Buat yudisium</x-button></div>
        </x-card>
    </form>

    <x-card :padding="false">
        @forelse ($yudisiums as $yudisium)
            <a href="{{ route('yudisium.show', $yudisium) }}" wire:navigate wire:key="y-{{ $yudisium->id }}" class="flex min-h-14 flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0 hover:bg-surface-muted">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ $yudisium->name }}</p>
                    <p class="truncate text-xs text-text-muted">{{ $yudisium->held_on->translatedFormat('j F Y') }}{{ $yudisium->number ? ' · '.$yudisium->number : '' }} · {{ $yudisium->decisions_count }} lulus</p>
                </div>
                <x-badge :variant="$yudisium->isFinalized() ? 'success' : 'warning'">{{ $yudisium->isFinalized() ? 'Ditetapkan' : 'Draf' }}</x-badge>
            </a>
        @empty
            <x-empty-state icon="academic-cap" title="Belum ada yudisium" description="Buat yudisium untuk mengevaluasi calon lulusan." />
        @endforelse
    </x-card>
</div>
