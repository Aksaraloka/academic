<div class="space-y-6">
    <x-page-header :title="'Pendaftar · '.$period->name" subtitle="Periksa berkas lalu setujui atau tolak dengan alasan.">
        <x-slot:actions>
            <x-button :href="route('wisuda.overview', ['periode' => $period->id])" wire:navigate variant="secondary" icon="arrow-left">Pendaftaran wisuda</x-button>
            <x-button :href="route('wisuda.minutes', $period)" wire:navigate icon="document-text">Berita acara</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($period->isFinalized())
        <p class="rounded-lg border border-border bg-surface-muted p-3 text-sm text-text-muted" role="status">Berita acara sudah diterbitkan, jadi keputusan verifikasi dikunci.</p>
    @endif

    <div>
        <label for="status" class="sr-only">Filter status</label>
        <select id="status" wire:model.live="filter" class="input sm:max-w-xs">
            <option value="">Semua status</option>
            @foreach ($statuses as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach
        </select>
    </div>

    <x-card :padding="false">
        @forelse ($registrations as $row)
            <div class="space-y-3 border-t border-border px-4 py-3 first:border-t-0" wire:key="reg-{{ $row->id }}">
                <div class="flex flex-wrap items-start gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold">{{ $row->student->name }}</p>
                        <p class="truncate text-xs text-text-muted tabular-nums">{{ $row->student->nim }} · {{ $row->student->program?->label() ?? 'Tanpa prodi' }}</p>
                    </div>
                    <x-badge :variant="$row->status->variant()" :icon="$row->status->icon()">{{ $row->status->label() }}</x-badge>
                </div>
                <p class="text-sm">{{ $row->thesis_title }}</p>
                <p class="text-xs text-text-muted tabular-nums">IPK {{ number_format((float) $row->gpa, 2, ',', '.') }} · {{ $row->credits_earned }} SKS · Sidang {{ $row->defense_date->translatedFormat('j M Y') }}@if ($row->gown_size) · Toga {{ $row->gown_size }}@endif</p>

                @if ($row->rejection_reason)
                    <p class="text-sm text-danger">Alasan penolakan: {{ $row->rejection_reason }}</p>
                @endif

                @if ($canDecide && $row->status !== \App\Enums\GraduationStatus::Approved)
                    @if ($rejectingId === $row->id)
                        <form wire:submit="reject" class="space-y-2">
                            <x-field label="Alasan penolakan" name="reason" required>
                                <input id="reason-{{ $row->id }}" type="text" wire:model="reason" class="input" autofocus>
                            </x-field>
                            <div class="flex flex-wrap gap-2">
                                <x-button type="submit" variant="danger" size="sm" wire:loading.attr="disabled" wire:target="reject">Tolak pendaftaran</x-button>
                                <x-button variant="secondary" size="sm" wire:click="cancelReject">Batal</x-button>
                            </div>
                        </form>
                    @elseif ($row->status === \App\Enums\GraduationStatus::Submitted)
                        <div class="flex flex-wrap gap-2">
                            <x-button size="sm" icon="check" wire:click="approve({{ $row->id }})" wire:loading.attr="disabled" wire:target="approve">Setujui</x-button>
                            <x-button size="sm" variant="secondary" wire:click="startReject({{ $row->id }})">Tolak</x-button>
                        </div>
                    @endif
                @endif
            </div>
        @empty
            <x-empty-state icon="academic-cap" title="Belum ada pendaftar" description="Pendaftar akan muncul di sini setelah mahasiswa mengajukan." />
        @endforelse
    </x-card>
</div>
