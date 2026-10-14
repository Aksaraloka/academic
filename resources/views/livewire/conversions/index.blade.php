<div class="space-y-6">
    <x-page-header title="Konversi SKS mahasiswa" subtitle="Nilai tiap mata kuliah pengganti. Yang disetujui langsung masuk transkrip, IPK, dan SKS lulus.">
        <x-slot:actions>
            <x-button :href="route('internships.index')" wire:navigate variant="secondary" icon="building-office">Magang dan MBKM</x-button>
        </x-slot:actions>
    </x-page-header>

    @foreach (['decision', 'note'] as $key)@error($key)<p class="rounded-lg border border-danger/40 bg-danger/10 p-3 text-sm text-danger" role="alert">{{ $message }}</p>@enderror @endforeach

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-field label="Status" name="status">
            <select id="status" wire:model.live="status" class="input">
                <option value="">Semua status</option>
                @foreach ($statuses as $option)<option value="{{ $option->value }}">{{ $option->label() }} ({{ $counts[$option->value] ?? 0 }})</option>@endforeach
            </select>
        </x-field>
        <x-field label="Cari" name="search"><input id="search" type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="Nama atau NIM"></x-field>
    </div>

    @if ($reviewing)
        <x-card title="Nilai pengajuan konversi">
            <x-info-list :items="array_filter([
                'Mahasiswa' => $reviewing->student->name.' · '.$reviewing->student->nim,
                'Sumber' => $reviewing->source->label(),
                'Program' => $reviewing->internship ? $reviewing->internship->partner_name.' ('.$reviewing->internship->type->label().', nilai '.$reviewing->internship->final_letter.')' : null,
                'Dasar konversi' => $reviewing->description,
            ])" />
            <div class="mt-4 divide-y divide-border rounded-lg border border-border">
                @foreach ($reviewing->items as $item)
                    <div class="flex items-center gap-3 px-3 py-2" wire:key="rvi-{{ $item->id }}">
                        <div class="min-w-0 flex-1"><p class="truncate text-sm font-medium">{{ $item->subject->name }}</p><p class="text-xs text-text-muted">{{ $item->subject->code }} · {{ $item->credits }} SKS</p></div>
                        <label class="sr-only" for="letter-{{ $item->id }}">Huruf mutu {{ $item->subject->name }}</label>
                        <select id="letter-{{ $item->id }}" wire:model="letters.{{ $item->id }}" class="input w-24">@foreach ($gradeLetters as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</select>
                    </div>
                @endforeach
            </div>
            <div class="mt-4 max-w-xl"><x-field label="Catatan" name="note" hint="Wajib diisi bila menolak."><input id="note" type="text" wire:model="note" class="input" maxlength="500"></x-field></div>
            <div class="mt-4 flex flex-wrap gap-2">
                <x-button wire:click="approve" wire:confirm="Setujui konversi ini dan masukkan ke transkrip?" wire:loading.attr="disabled" wire:target="approve">Setujui</x-button>
                <x-button variant="secondary" wire:click="reject">Tolak</x-button>
                <x-button variant="ghost" wire:click="$set('reviewingId', null)">Tutup</x-button>
            </div>
        </x-card>
    @endif

    <x-card :padding="false">
        @forelse ($conversions as $conversion)
            <div class="flex min-h-14 flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0" wire:key="cvr-{{ $conversion->id }}">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ $conversion->student->name }} · {{ $conversion->source->label() }}</p>
                    <p class="truncate text-xs text-text-muted">{{ $conversion->student->nim }} · {{ $conversion->items->count() }} mata kuliah, {{ $conversion->items->sum('credits') }} SKS · {{ $conversion->created_at->translatedFormat('j M Y') }}</p>
                </div>
                <x-badge :variant="$conversion->status->variant()">{{ $conversion->status->label() }}</x-badge>
                @if ($conversion->status === \App\Enums\ConversionStatus::Submitted)<x-button size="sm" wire:click="review({{ $conversion->id }})">Nilai</x-button>@endif
            </div>
        @empty
            <x-empty-state icon="arrow-path" title="Tidak ada pengajuan" description="Pengajuan konversi SKS muncul di sini." />
        @endforelse
    </x-card>
</div>
