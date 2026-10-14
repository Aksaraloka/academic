<div class="space-y-6">
    <x-page-header title="Kalender akademik" subtitle="Masa KRS, perkuliahan, UTS, UAS, batas input nilai, dan cuti akademik per semester.">
        @if ($canManage && auth()->user()->role === \App\Enums\Role::Admin)
            <x-slot:actions>
                <x-button :href="route('academic.terms')" wire:navigate variant="secondary" icon="cog-6-tooth">Kelola semester</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    @if ($term)
        <div class="max-w-xs">
            <x-field label="Semester" name="termId">
                <select id="termId" wire:model.live="termId" class="input">
                    @foreach ($terms as $option)
                        <option value="{{ $option->id }}" @selected($option->id === $term->id)>{{ $option->name }} · {{ $option->semester }}{{ $option->is_active ? ' (aktif)' : '' }}</option>
                    @endforeach
                </select>
            </x-field>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <x-card title="Sedang berlangsung">
                @forelse ($ongoing as $entry)
                    <div class="flex items-center gap-2 py-1.5" wire:key="on-{{ $loop->index }}">
                        <x-badge :variant="$entry['type']->variant()" :icon="$entry['type']->icon()">{{ $entry['title'] }}</x-badge>
                        <span class="text-xs text-text-muted">sampai {{ $entry['ends_on']->translatedFormat('j M Y') }}</span>
                    </div>
                @empty
                    <p class="text-sm text-text-muted">Tidak ada agenda yang berlangsung hari ini.</p>
                @endforelse
            </x-card>
            <x-card title="Segera datang">
                @forelse ($upcoming as $entry)
                    <div class="flex items-center gap-2 py-1.5" wire:key="up-{{ $loop->index }}">
                        <x-badge :variant="$entry['type']->variant()" :icon="$entry['type']->icon()">{{ $entry['title'] }}</x-badge>
                        <span class="text-xs text-text-muted">mulai {{ $entry['starts_on']->translatedFormat('j M Y') }} ({{ $entry['starts_on']->diffForHumans() }})</span>
                    </div>
                @empty
                    <p class="text-sm text-text-muted">Belum ada agenda mendatang.</p>
                @endforelse
            </x-card>
        </div>

        @if ($canManage)
            <form wire:submit="save">
                <x-card :title="$editingId ? 'Ubah agenda' : 'Agenda baru'">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <x-field label="Jenis" name="type" required>
                            <select id="type" wire:model="type" class="input">
                                @foreach ($storableTypes as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach
                            </select>
                        </x-field>
                        <div class="lg:col-span-3">
                            <x-field label="Judul" name="title" hint="Kosongkan untuk memakai nama jenis agenda.">
                                <input id="title" type="text" wire:model="title" class="input" placeholder="UTS Semester Ganjil">
                            </x-field>
                        </div>
                        <x-field label="Mulai" name="starts_on" required><input id="starts_on" type="date" wire:model="starts_on" class="input"></x-field>
                        <x-field label="Selesai" name="ends_on" required><input id="ends_on" type="date" wire:model="ends_on" class="input"></x-field>
                        <div class="sm:col-span-2">
                            <x-field label="Catatan" name="notes"><input id="notes" type="text" wire:model="notes" class="input" placeholder="Opsional"></x-field>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-button type="submit" wire:loading.attr="disabled" wire:target="save">{{ $editingId ? 'Simpan perubahan' : 'Tambah agenda' }}</x-button>
                        @if ($editingId)<x-button type="button" variant="secondary" wire:click="cancel">Batal</x-button>@endif
                    </div>
                    <p class="mt-3 text-xs text-text-muted">Perkuliahan dan batas input nilai diatur di menu Semester akademik.</p>
                </x-card>
            </form>
        @endif

        <x-card :title="$term->name.' · '.$term->semester" :padding="false">
            @foreach ($entries as $entry)
                @php $past = $entry['ends_on']->isPast() && ! $entry['ends_on']->isToday(); @endphp
                <div class="flex min-h-14 flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0 {{ $past ? 'opacity-60' : '' }}" wire:key="entry-{{ $entry['id'] ?? 'auto-'.$entry['type']->value }}">
                    <x-icon :name="$entry['type']->icon()" class="text-primary" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ $entry['title'] }}</p>
                        <p class="truncate text-xs text-text-muted">
                            {{ $entry['starts_on']->translatedFormat('j M Y') }}@if (! $entry['starts_on']->isSameDay($entry['ends_on'])) – {{ $entry['ends_on']->translatedFormat('j M Y') }}@endif
                            @if ($entry['notes']) · {{ $entry['notes'] }}@endif
                        </p>
                    </div>
                    <x-badge :variant="$entry['type']->variant()">{{ $entry['type']->label() }}</x-badge>
                    @if ($canManage && $entry['id'])
                        <div class="flex gap-2">
                            <x-button size="sm" variant="secondary" wire:click="edit({{ $entry['id'] }})">Ubah</x-button>
                            <x-button size="sm" variant="secondary" wire:click="delete({{ $entry['id'] }})" wire:confirm="Hapus agenda ini?">Hapus</x-button>
                        </div>
                    @endif
                </div>
            @endforeach
        </x-card>
    @else
        <x-card><x-empty-state icon="calendar-days" title="Belum ada semester" description="Admin perlu menambahkan semester akademik lebih dulu." /></x-card>
    @endif
</div>
