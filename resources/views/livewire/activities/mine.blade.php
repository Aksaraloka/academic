<div class="space-y-6">
    <x-page-header title="Aktivitas kemahasiswaan" subtitle="Catat organisasi, prestasi, dan sertifikat. Yang sudah diverifikasi masuk ke SKPI.">
        <x-slot:actions>
            <x-button :href="route('activities.skpi')" icon="arrow-down-tray" download>Unduh rekap SKPI</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3">
        <x-stat-tile label="Kegiatan terverifikasi" :value="$skpi['count']" />
        <x-stat-tile label="Total poin SKPI" :value="$skpi['total_points']" tone="text-primary" />
    </div>

    @error('activity')<p class="rounded-lg border border-danger/40 bg-danger/10 p-3 text-sm text-danger" role="alert">{{ $message }}</p>@enderror

    <form wire:submit="save">
        <x-card title="Tambah kegiatan">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Kategori" name="category" required>
                    <select id="category" wire:model.live="category" class="input">@foreach ($categories as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach</select>
                </x-field>
                <x-field label="Tingkat" name="level" required>
                    <select id="level" wire:model="level" class="input">@foreach ($levels as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach</select>
                </x-field>
                <div class="sm:col-span-2"><x-field label="Nama kegiatan" name="title" required><input id="title" type="text" wire:model="title" class="input" @error('title') aria-invalid="true" @enderror></x-field></div>
                <x-field label="Penyelenggara / organisasi" name="organizer" required><input id="organizer" type="text" wire:model="organizer" class="input"></x-field>
                <x-field :label="$selected->roleLabel()" name="role_or_result"><input id="role_or_result" type="text" wire:model="role_or_result" class="input"></x-field>
                <x-field label="Mulai" name="started_on" required><input id="started_on" type="date" wire:model="started_on" class="input" max="{{ today()->toDateString() }}"></x-field>
                <x-field label="Selesai" name="ended_on" hint="Kosongkan bila satu hari atau masih berjalan."><input id="ended_on" type="date" wire:model="ended_on" class="input"></x-field>
                <div class="sm:col-span-2"><x-field label="Uraian singkat" name="description"><textarea id="description" wire:model="description" rows="3" class="input"></textarea></x-field></div>
                <div class="sm:col-span-2">
                    <x-field label="Bukti (sertifikat, SK, atau foto)" name="evidence" hint="JPG, PNG, WebP, atau PDF, maksimal 3 MB. Bukti mempercepat verifikasi.">
                        <input id="evidence" type="file" wire:model="evidence" accept=".jpg,.jpeg,.png,.webp,.pdf" class="input">
                    </x-field>
                </div>
            </div>
            <div class="mt-4"><x-button type="submit" wire:loading.attr="disabled" wire:target="save,evidence">Kirim untuk diverifikasi</x-button></div>
        </x-card>
    </form>

    <x-card title="Kegiatanku" :padding="false">
        @forelse ($activities as $activity)
            <div class="border-t border-border px-4 py-3 first:border-t-0" wire:key="act-{{ $activity->id }}">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="min-w-0 flex-1 text-sm font-medium">{{ $activity->title }}</p>
                    <x-badge :variant="$activity->status->variant()">{{ $activity->status->label() }}</x-badge>
                    @if ($activity->points !== null)<x-badge variant="primary">{{ $activity->points }} poin</x-badge>@endif
                </div>
                <p class="mt-0.5 text-xs text-text-muted">{{ $activity->category->label() }} · {{ $activity->level->label() }} · {{ $activity->organizer }}@if ($activity->role_or_result) · {{ $activity->role_or_result }}@endif · {{ $activity->periodLabel() }}</p>
                @if ($activity->review_note)<p class="mt-1 text-xs {{ $activity->status === \App\Enums\ActivityStatus::Rejected ? 'text-danger' : 'text-text-muted' }}">Catatan verifikator: {{ $activity->review_note }}</p>@endif
                <div class="mt-2 flex flex-wrap gap-2">
                    @if ($activity->evidence_path)<x-button size="sm" variant="secondary" :href="route('activities.evidence', $activity)" icon="paper-clip" target="_blank" rel="noopener">Lihat bukti</x-button>@endif
                    @if ($activity->status !== \App\Enums\ActivityStatus::Verified)<x-button size="sm" variant="ghost" wire:click="delete({{ $activity->id }})" wire:confirm="Hapus kegiatan ini?" icon="trash">Hapus</x-button>@endif
                </div>
            </div>
        @empty
            <x-empty-state icon="star" title="Belum ada kegiatan" description="Tambahkan organisasi, kepanitiaan, prestasi, atau sertifikat yang pernah kamu ikuti." />
        @endforelse
    </x-card>
</div>
