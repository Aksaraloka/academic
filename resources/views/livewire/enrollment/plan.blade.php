@php
    $scheduleText = fn ($assignment) => $assignment->schedules->map(fn ($schedule) => $schedule->timeSlot->dayName().' '.$schedule->timeSlot->timeRange())->implode(', ') ?: 'Jadwal belum diatur';
@endphp
<div class="space-y-6">
    <x-page-header title="Kartu Rencana Studi" :subtitle="$term ? 'Semester '.$term->name.' · '.$term->semester : 'Pilih mata kuliah yang akan kamu ambil semester ini.'">
        <x-slot:actions>
            <x-button :href="route('academic.calendar')" wire:navigate variant="secondary" icon="calendar-days">Kalender akademik</x-button>
        </x-slot:actions>
    </x-page-header>

    @if (! $term)
        <x-card><x-empty-state icon="calendar-days" title="Belum ada semester aktif" description="KRS tersedia setelah BAAK mengaktifkan semester." /></x-card>
    @else
        @if ($inactiveStatus)
            <div class="rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm" role="status">
                Status akademikmu <strong>{{ $inactiveStatus->label() }}</strong>, jadi KRS tidak bisa diisi.
                @if ($inactiveStatus->canReactivate())<a href="{{ route('status.mine') }}" wire:navigate class="font-medium text-primary hover:underline">Ajukan aktif kembali</a>.@endif
            </div>
        @endif

        @if ($unpaid !== null)
            <div class="rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm" role="status">
                KRS baru bisa diisi setelah tagihan semester ini lunas. Sisa tagihan <strong>{{ \App\Services\FinanceService::rupiah($unpaid) }}</strong>.
                <a href="{{ route('pay.index') }}" wire:navigate class="font-medium text-primary hover:underline">Buka Biaya Pendidikan</a>.
            </div>
        @endif

        <x-card>
            <div class="flex flex-wrap items-center gap-3">
                @if ($windowOpen)
                    <x-badge variant="success" icon="check-circle">Masa KRS dibuka</x-badge>
                    <span class="text-sm text-text-muted">sampai {{ $window['ends_on']->translatedFormat('j F Y') }}</span>
                @elseif ($window)
                    <x-badge variant="warning" icon="lock-closed">Masa KRS ditutup</x-badge>
                    <span class="text-sm text-text-muted">{{ $window['starts_on']->isFuture() ? 'Dibuka '.$window['starts_on']->translatedFormat('j F Y') : 'Ditutup sejak '.$window['ends_on']->translatedFormat('j F Y') }}</span>
                @else
                    <x-badge variant="warning" icon="lock-closed">Masa KRS belum diumumkan</x-badge>
                @endif
                @if ($enrollment)
                    <x-badge :variant="$enrollment->status->variant()" class="ml-auto">{{ $enrollment->status->label() }}</x-badge>
                @endif
            </div>

            @if ($enrollment?->status === \App\Enums\EnrollmentStatus::Rejected && $enrollment->advisor_note)
                <div class="mt-3 rounded-lg border border-danger/40 bg-danger/10 p-3 text-sm" role="alert">
                    <p class="font-semibold">KRS ditolak{{ $enrollment->decider ? ' oleh '.$enrollment->decider->name : '' }}</p>
                    <p class="mt-1">{{ $enrollment->advisor_note }}</p>
                </div>
            @elseif ($enrollment?->status === \App\Enums\EnrollmentStatus::Approved)
                <p class="mt-3 text-sm text-text-muted">Disetujui{{ $enrollment->decider ? ' oleh '.$enrollment->decider->name : '' }} pada {{ $enrollment->decided_at?->translatedFormat('j F Y') }}.@if ($enrollment->advisor_note) Catatan: {{ $enrollment->advisor_note }}@endif</p>
            @endif
        </x-card>

        <div class="grid gap-4 sm:grid-cols-3">
            <x-stat-tile label="SKS dipilih" :value="$editable ? $chosenCredits : ($enrollment?->credits() ?? 0)" />
            <x-stat-tile label="Batas SKS" :value="$maxCredits" />
            <x-stat-tile label="Mata kuliah" :value="$editable ? count($selected) : $submittedList->count()" />
        </div>

        @error('selection')<p class="rounded-lg border border-danger/40 bg-danger/10 p-3 text-sm text-danger" role="alert">{{ $message }}</p>@enderror

        @if ($editable)
            @if ($conflicts)
                <div class="rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm" role="status">
                    <p class="font-semibold">Jadwal bentrok</p>
                    <ul class="mt-1 list-disc pl-5">@foreach ($conflicts as $message)<li>{{ $message }}</li>@endforeach</ul>
                </div>
            @endif

            <form wire:submit.prevent class="space-y-4">
                <x-card title="Mata kuliah yang dibuka" :padding="false">
                    @forelse ($offerings as $assignment)
                        @php
                            $unmet = $assignment->krs_unmet;
                            $full = $assignment->capacity !== null && $assignment->krs_seats_taken >= $assignment->capacity && ! in_array((string) $assignment->id, $selected, true);
                            $blocked = $unmet->isNotEmpty() || $full;
                        @endphp
                        <label class="flex min-h-14 items-start gap-3 border-t border-border px-4 py-3 first:border-t-0 {{ $blocked ? 'opacity-60' : 'cursor-pointer hover:bg-surface-muted' }}" wire:key="off-{{ $assignment->id }}">
                            <input type="checkbox" wire:model.live="selected" value="{{ $assignment->id }}" class="mt-1 size-5 shrink-0 accent-primary" @disabled($blocked)>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium">{{ $assignment->subject->code }} · {{ $assignment->subject->name }}</span>
                                <span class="block text-xs text-text-muted">{{ $assignment->teacher->name }} · Kelas {{ $assignment->classLabel() }} · {{ $scheduleText($assignment) }}</span>
                                @if ($unmet->isNotEmpty())<span class="block text-xs text-danger">Prasyarat belum lulus: {{ $unmet->pluck('name')->implode(', ') }}</span>@endif
                                @if ($assignment->capacity !== null)<span class="block text-xs {{ $full ? 'text-danger' : 'text-text-muted' }}">Kuota {{ min($assignment->krs_seats_taken, $assignment->capacity) }}/{{ $assignment->capacity }}{{ $full ? ' · penuh' : '' }}</span>@endif
                            </span>
                            <span class="flex shrink-0 flex-wrap justify-end gap-1">
                                <x-badge>{{ $assignment->subject->credits }} SKS</x-badge>
                                @if ($assignment->krs_type)<x-badge :variant="$assignment->krs_type->variant()">{{ $assignment->krs_type->label() }}</x-badge>@endif
                                @if ($assignment->krs_semester)<x-badge>Smt {{ $assignment->krs_semester }}</x-badge>@endif
                                @if ($assignment->study_group_id === $ownGroupId)<x-badge variant="primary">Paket kelasmu</x-badge>@endif
                            </span>
                        </label>
                    @empty
                        <x-empty-state icon="clipboard-document-list" title="Belum ada mata kuliah dibuka" description="BAAK belum membuka mata kuliah untuk prodimu semester ini." />
                    @endforelse
                </x-card>

                <div class="flex flex-wrap gap-2">
                    <x-button type="button" variant="secondary" wire:click="save" wire:loading.attr="disabled" wire:target="save,submit">Simpan draf</x-button>
                    <x-button type="button" wire:click="submit" wire:confirm="Ajukan KRS ke dosen wali? Pilihan tidak bisa diubah sampai ditarik atau ditolak." wire:loading.attr="disabled" wire:target="save,submit">Ajukan ke dosen wali</x-button>
                </div>
            </form>
        @elseif ($enrollment)
            <x-card title="Mata kuliah di KRS" :padding="false">
                @forelse ($submittedList as $assignment)
                    <div class="flex min-h-14 items-start gap-3 border-t border-border px-4 py-3 first:border-t-0" wire:key="krs-{{ $assignment->id }}">
                        <x-icon name="academic-cap" class="mt-0.5 text-primary" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium">{{ $assignment->subject->code }} · {{ $assignment->subject->name }}</p>
                            <p class="text-xs text-text-muted">{{ $assignment->teacher->name }} · Kelas {{ $assignment->classLabel() }} · {{ $scheduleText($assignment) }}</p>
                        </div>
                        <x-badge>{{ $assignment->subject->credits }} SKS</x-badge>
                    </div>
                @empty
                    <p class="px-4 py-4 text-sm text-text-muted">Belum ada mata kuliah di KRS ini.</p>
                @endforelse
            </x-card>

            @if ($enrollment->status === \App\Enums\EnrollmentStatus::Submitted)
                <div><x-button type="button" variant="secondary" wire:click="withdraw" wire:confirm="Tarik pengajuan KRS?">Tarik pengajuan</x-button></div>
            @elseif (! $windowOpen && $enrollment->status->isEditable())
                <p class="text-sm text-text-muted">Masa KRS sudah ditutup. Hubungi BAAK bila perlu perubahan.</p>
            @endif
        @else
            <x-card><x-empty-state icon="lock-closed" title="KRS belum bisa diisi" description="Tunggu masa pengisian KRS dibuka. Jadwalnya ada di Kalender akademik." /></x-card>
        @endif
    @endif
</div>
