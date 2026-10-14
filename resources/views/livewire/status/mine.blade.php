@php
    $canLeave = $student->status === \App\Enums\StudentStatus::Active;
    $canReactivate = $student->status->canReactivate();
@endphp
<div class="mx-auto max-w-4xl space-y-6">
    <x-page-header title="Status akademik" subtitle="Status aktif, cuti, atau non-aktif per semester, beserta pengajuan cuti dan aktif kembali.">
        <x-slot:actions>
            <x-button :href="route('academic.calendar')" wire:navigate variant="secondary" icon="calendar-days">Kalender akademik</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <div class="flex flex-wrap items-center gap-3">
            <span class="text-sm text-text-muted">Status sekarang</span>
            <x-badge :variant="$student->status->variant()">{{ $student->status->label() }}</x-badge>
            @if ($term)<span class="text-sm text-text-muted">Semester {{ $term->name }} · {{ $term->semester }}</span>@endif
        </div>
    </x-card>

    @if ($canLeave || $canReactivate)
        <form wire:submit="submit">
            <x-card :title="$canReactivate ? 'Ajukan aktif kembali' : 'Ajukan cuti akademik'">
                @if ($canLeave)
                    <p class="mb-3 text-sm {{ $leaveOpen ? 'text-text-muted' : 'text-warning' }}">
                        {{ $leaveOpen ? 'Masa pengajuan cuti sedang dibuka. Selama cuti kamu tidak bisa mengisi KRS.' : 'Masa pengajuan cuti akademik sedang ditutup. Lihat jadwalnya di Kalender akademik.' }}
                    </p>
                @endif
                <x-field label="Alasan" name="reason" required>
                    <textarea id="reason" wire:model="reason" rows="3" class="input" placeholder="Jelaskan alasanmu minimal 10 karakter" @error('reason') aria-invalid="true" @enderror></textarea>
                </x-field>
                <div class="mt-4"><x-button type="submit" wire:loading.attr="disabled" wire:target="submit" :disabled="$canLeave && ! $leaveOpen">Kirim pengajuan</x-button></div>
            </x-card>
        </form>
    @endif

    <x-card title="Pengajuan saya" :padding="false">
        @forelse ($requests as $request)
            <div class="flex min-h-14 flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0" wire:key="req-{{ $request->id }}">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium">{{ $request->type->label() }} · {{ $request->academicTerm->name }} {{ $request->academicTerm->semester }}</p>
                    <p class="text-xs text-text-muted">{{ $request->reason }}</p>
                    @if ($request->decision_note)<p class="text-xs text-text-muted">Catatan: {{ $request->decision_note }}</p>@endif
                </div>
                <x-badge :variant="$request->status->variant()">{{ $request->status->label() }}</x-badge>
            </div>
        @empty
            <p class="px-4 py-4 text-sm text-text-muted">Belum ada pengajuan.</p>
        @endforelse
    </x-card>

    <x-card title="Riwayat status per semester" :padding="false">
        @forelse ($history as $row)
            <div class="flex min-h-12 flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0" wire:key="hist-{{ $row->id }}">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium">{{ $row->academicTerm->name }} · {{ $row->academicTerm->semester }}</p>
                    @if ($row->reason)<p class="truncate text-xs text-text-muted">{{ $row->reason }}</p>@endif
                </div>
                <x-badge :variant="$row->status->variant()">{{ $row->status->label() }}</x-badge>
            </div>
        @empty
            <p class="px-4 py-4 text-sm text-text-muted">Belum ada perubahan status. Semester tanpa catatan berarti berstatus aktif.</p>
        @endforelse
    </x-card>
</div>
