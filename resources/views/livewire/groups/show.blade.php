<div class="space-y-6">
    <x-page-header :title="$group->name" :subtitle="'Dosen wali: '.($group->homeroomTeacher?->name ?? 'belum ditentukan')">
        <x-slot:actions>
            <x-button :href="route('groups.index')" wire:navigate variant="secondary" icon="arrow-left">Kembali</x-button>
            <x-button :href="route('reports.index', ['group' => $group->id])" wire:navigate icon="chart-bar">Rekap kehadiran</x-button>
        </x-slot:actions>
    </x-page-header>

    @if (auth()->user()->canManageStudyGroups())
        <form wire:submit="saveSettings">
            <x-card title="Pengaturan kelas">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Dosen wali" name="homeroom_teacher_id">
                        <select id="homeroom_teacher_id" wire:model="homeroom_teacher_id" class="input">
                            <option value="">Belum ditentukan</option>
                            @foreach ($teachers as $teacher)<option value="{{ $teacher->id }}">{{ $teacher->name }}</option>@endforeach
                        </select>
                    </x-field>
                    <x-field label="Tingkat" name="grade_level_id" hint="Mahasiswa baru dari Admission ditempatkan ke kelas tingkat pertama.">
                        <select id="grade_level_id" wire:model="grade_level_id" class="input">
                            <option value="">Belum ditentukan</option>
                            @foreach ($gradeLevels as $level)<option value="{{ $level->id }}">{{ $level->name }}</option>@endforeach
                        </select>
                    </x-field>
                </div>
                <div class="mt-4"><x-button type="submit">Simpan perubahan</x-button></div>
            </x-card>
        </form>
    @endif

    <x-card :title="'Anggota ('.$members->count().')'" :padding="false">
        @forelse ($members as $member)
            <div class="flex min-h-12 items-center gap-3 border-t border-border px-4 py-2 first:border-t-0 hover:bg-surface-muted" wire:key="member-{{ $member->id }}">
                <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ $member->name }}</span>
                @if (in_array($member->id, $newIds, true))
                    <x-badge variant="info">Baru</x-badge>
                @endif
                <span class="text-xs tabular-nums text-text-muted">{{ $member->nim }}</span>
                @if (auth()->user()->canManageStudyGroups())
                    @if ($otherGroups->isNotEmpty())
                        <label class="sr-only" for="move-{{ $member->id }}">Pindahkan {{ $member->name }} ke kelas lain</label>
                        <select id="move-{{ $member->id }}" class="input !min-h-11 !w-auto text-xs"
                                wire:change="move({{ $member->id }}, $event.target.value)" wire:loading.attr="disabled" wire:target="move">
                            <option value="">Pindah ke…</option>
                            @foreach ($otherGroups as $other)<option value="{{ $other->id }}">{{ $other->name }}</option>@endforeach
                        </select>
                    @endif
                    <x-button variant="ghost" size="sm" icon="x-mark" wire:click="remove({{ $member->id }})" wire:confirm="Keluarkan {{ $member->name }} dari kelas ini?">
                        <span class="sr-only">Keluarkan {{ $member->name }}</span>
                    </x-button>
                @endif
            </div>
        @empty
            <x-empty-state icon="users" title="Kelas masih kosong" description="Pilih mahasiswa dari daftar belum ditempatkan di bawah." />
        @endforelse
    </x-card>

    @if (auth()->user()->canManageStudyGroups())
        <x-card title="Tempatkan mahasiswa" :padding="false">
            <div class="border-b border-border p-4">
                <label for="cari" class="sr-only">Cari mahasiswa</label>
                <input id="cari" type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="Cari mahasiswa belum ditempatkan">
            </div>
            @forelse ($unplaced as $student)
                <label class="flex min-h-12 items-center gap-3 border-t border-border px-4 py-2 first:border-t-0 hover:bg-surface-muted" wire:key="unplaced-{{ $student->id }}">
                    <input type="checkbox" wire:model="selected" value="{{ $student->id }}" class="size-5 accent-primary">
                    <span class="min-w-0 flex-1 truncate text-sm">{{ $student->name }}</span>
                    <span class="text-xs tabular-nums text-text-muted">{{ $student->nim }}</span>
                </label>
            @empty
                <x-empty-state icon="check-circle" title="Semua mahasiswa aktif sudah ditempatkan" />
            @endforelse
            @if ($unplaced->isNotEmpty())
                <div class="border-t border-border p-4">
                    <x-button wire:click="place" wire:loading.attr="disabled" wire:target="place" :disabled="false">Tempatkan mahasiswa terpilih</x-button>
                </div>
            @endif
        </x-card>
    @endif
</div>
