<div class="space-y-6">
    <x-page-header :title="$group->name" :subtitle="'Wali kelas: '.($group->homeroomTeacher?->name ?? 'belum ditentukan')">
        <x-slot:actions>
            <x-button :href="route('groups.index')" wire:navigate variant="secondary" icon="arrow-left">Kembali</x-button>
            <x-button :href="route('reports.index', ['group' => $group->id])" wire:navigate icon="chart-bar">Rekap kehadiran</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card :title="'Anggota ('.$members->count().')'" :padding="false">
        @forelse ($members as $member)
            <div class="flex min-h-12 items-center gap-3 border-t border-border px-4 py-2 first:border-t-0 hover:bg-surface-muted" wire:key="member-{{ $member->id }}">
                <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ $member->name }}</span>
                <span class="text-xs tabular-nums text-text-muted">{{ $member->nis }}</span>
                @if (auth()->user()->isAdmin())
                    <x-button variant="ghost" size="sm" icon="x-mark" wire:click="remove({{ $member->id }})" wire:confirm="Keluarkan {{ $member->name }} dari rombel ini?">
                        <span class="sr-only">Keluarkan {{ $member->name }}</span>
                    </x-button>
                @endif
            </div>
        @empty
            <x-empty-state icon="users" title="Rombel masih kosong" description="Pilih siswa dari daftar belum ditempatkan di bawah." />
        @endforelse
    </x-card>

    @if (auth()->user()->isAdmin())
        <x-card title="Tempatkan siswa" :padding="false">
            <div class="border-b border-border p-4">
                <label for="cari" class="sr-only">Cari siswa</label>
                <input id="cari" type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="Cari siswa belum ditempatkan">
            </div>
            @forelse ($unplaced as $student)
                <label class="flex min-h-12 items-center gap-3 border-t border-border px-4 py-2 first:border-t-0 hover:bg-surface-muted" wire:key="unplaced-{{ $student->id }}">
                    <input type="checkbox" wire:model="selected" value="{{ $student->id }}" class="size-5 accent-primary">
                    <span class="min-w-0 flex-1 truncate text-sm">{{ $student->name }}</span>
                    <span class="text-xs tabular-nums text-text-muted">{{ $student->nis }}</span>
                </label>
            @empty
                <x-empty-state icon="check-circle" title="Semua siswa aktif sudah ditempatkan" />
            @endforelse
            @if ($unplaced->isNotEmpty())
                <div class="border-t border-border p-4">
                    <x-button wire:click="place" wire:loading.attr="disabled" wire:target="place" :disabled="false">Tempatkan siswa terpilih</x-button>
                </div>
            @endif
        </x-card>
    @endif
</div>
