<div class="space-y-6">
    <x-page-header title="Verifikasi aktivitas mahasiswa" subtitle="Periksa bukti, tetapkan poin SKPI, atau tolak dengan alasan." />

    @foreach (['note', 'review'] as $key)@error($key)<p class="rounded-lg border border-danger/40 bg-danger/10 p-3 text-sm text-danger" role="alert">{{ $message }}</p>@enderror @endforeach

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-field label="Status" name="status">
            <select id="status" wire:model.live="status" class="input">
                <option value="">Semua status</option>
                @foreach ($statuses as $option)<option value="{{ $option->value }}">{{ $option->label() }} ({{ $counts[$option->value] ?? 0 }})</option>@endforeach
            </select>
        </x-field>
        <x-field label="Kategori" name="category">
            <select id="category" wire:model.live="category" class="input">
                <option value="">Semua kategori</option>
                @foreach ($categories as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach
            </select>
        </x-field>
        <x-field label="Cari" name="search"><input id="search" type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="Kegiatan, penyelenggara, nama, atau NIM"></x-field>
    </div>

    @if ($reviewing)
        <x-card title="Tinjau kegiatan">
            <x-info-list :items="array_filter([
                'Mahasiswa' => $reviewing->student->name.' · '.$reviewing->student->nim,
                'Kegiatan' => $reviewing->title,
                'Kategori' => $reviewing->category->label(),
                'Tingkat' => $reviewing->level->label(),
                'Penyelenggara' => $reviewing->organizer,
                $reviewing->category->roleLabel() => $reviewing->role_or_result,
                'Periode' => $reviewing->periodLabel(),
                'Uraian' => $reviewing->description,
            ])" />
            @if ($reviewing->evidence_path)
                <div class="mt-3"><x-button size="sm" variant="secondary" :href="route('activities.evidence', $reviewing)" icon="paper-clip" target="_blank" rel="noopener">Buka bukti</x-button></div>
            @else
                <p class="mt-3 text-sm text-warning">Mahasiswa tidak melampirkan bukti.</p>
            @endif
            <div class="mt-4 grid max-w-xl gap-4 sm:grid-cols-2">
                <x-field label="Poin SKPI" name="points" hint="Usulan dari kategori dan tingkat; boleh diubah."><input id="points" type="number" min="0" max="100" wire:model="points" class="input text-right tabular-nums" inputmode="numeric"></x-field>
                <x-field label="Catatan" name="note" hint="Wajib diisi bila menolak."><input id="note" type="text" wire:model="note" class="input" maxlength="500"></x-field>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <x-button wire:click="verify" wire:loading.attr="disabled" wire:target="verify">Verifikasi</x-button>
                <x-button variant="secondary" wire:click="reject">Tolak</x-button>
                <x-button variant="ghost" wire:click="$set('reviewingId', null)">Tutup</x-button>
            </div>
        </x-card>
    @endif

    <x-card :padding="false">
        @forelse ($activities as $activity)
            <div class="flex min-h-14 flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0" wire:key="actr-{{ $activity->id }}">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ $activity->title }}</p>
                    <p class="truncate text-xs text-text-muted">{{ $activity->student->name }} · {{ $activity->student->nim }} · {{ $activity->category->label() }} · {{ $activity->level->label() }} · {{ $activity->periodLabel() }}</p>
                </div>
                @if ($activity->points !== null)<x-badge variant="primary">{{ $activity->points }} poin</x-badge>@endif
                <x-badge :variant="$activity->status->variant()">{{ $activity->status->label() }}</x-badge>
                @if ($activity->status === \App\Enums\ActivityStatus::Pending)<x-button size="sm" wire:click="review({{ $activity->id }})">Tinjau</x-button>@endif
            </div>
        @empty
            <x-empty-state icon="star" title="Tidak ada kegiatan" description="Kegiatan yang dikirim mahasiswa muncul di sini." />
        @endforelse
    </x-card>
</div>
