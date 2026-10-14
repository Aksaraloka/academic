@php $fmt = fn ($value): string => $value === null ? '–' : rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ','); @endphp
<div class="space-y-6">
    <x-page-header title="Revisi nilai" :subtitle="$canDecide ? 'Putuskan pengajuan perubahan nilai dari dosen.' : 'Pengajuan perubahan nilai yang sudah dikunci.'">
        @if (auth()->user()->teacher)
            <x-slot:actions>
                <x-button :href="route('grades.input.index')" wire:navigate variant="secondary" icon="arrow-left">Input nilai</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    <div class="flex flex-wrap items-center gap-3">
        <div class="w-full max-w-xs">
            <label for="status" class="sr-only">Status</label>
            <select id="status" wire:model.live="filter" class="input">
                <option value="">Semua status</option>
                @foreach ($statuses as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach
            </select>
        </div>
        @if ($pendingCount > 0)<x-badge variant="info" icon="clock">{{ $pendingCount }} menunggu</x-badge>@endif
    </div>

    <div class="space-y-4">
        @forelse ($revisions as $revision)
            @php
                $grade = $revision->grade;
                $assignment = $grade->assignment;
                $old = $revision->old_scores;
                $new = $revision->new_scores;
            @endphp
            <x-card wire:key="rev-{{ $revision->id }}">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold">{{ $grade->student->name }} <span class="text-sm font-normal tabular-nums text-text-muted">· {{ $grade->student->nim }}</span></h2>
                        <p class="text-xs text-text-muted">{{ $assignment->subject->name }} · Kelas {{ $assignment->studyGroup->name }} · {{ $assignment->academicTerm->name }} {{ $assignment->academicTerm->semester }} · diajukan {{ $revision->requester?->name ?? 'dosen' }}, {{ $revision->created_at->translatedFormat('j M Y') }}</p>
                    </div>
                    <x-badge :variant="$revision->status->variant()" :icon="$revision->status->icon()">{{ $revision->status->label() }}</x-badge>
                </div>

                <table class="mt-4 w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-text-muted"><th class="py-1 pr-3 font-medium"> </th><th class="py-1 pr-3 text-right font-medium">Tugas</th><th class="py-1 pr-3 text-right font-medium">UTS</th><th class="py-1 pr-3 text-right font-medium">UAS</th><th class="py-1 pr-3 text-right font-medium">Nilai akhir</th><th class="py-1 font-medium">Huruf</th></tr>
                    </thead>
                    <tbody class="tabular-nums">
                        <tr><th scope="row" class="py-1 pr-3 text-left font-medium text-text-muted">Sebelum</th><td class="py-1 pr-3 text-right">{{ $fmt($old['assignment']) }}</td><td class="py-1 pr-3 text-right">{{ $fmt($old['midterm']) }}</td><td class="py-1 pr-3 text-right">{{ $fmt($old['final']) }}</td><td class="py-1 pr-3 text-right">{{ $fmt($old['total']) }}</td><td class="py-1">{{ $old['letter'] ?? '–' }}</td></tr>
                        <tr class="font-semibold"><th scope="row" class="py-1 pr-3 text-left">Sesudah</th><td class="py-1 pr-3 text-right">{{ $fmt($new['assignment']) }}</td><td class="py-1 pr-3 text-right">{{ $fmt($new['midterm']) }}</td><td class="py-1 pr-3 text-right">{{ $fmt($new['final']) }}</td><td class="py-1 pr-3 text-right">{{ $fmt($new['total'] ?? null) }}</td><td class="py-1">{{ $new['letter'] ?? '–' }}</td></tr>
                    </tbody>
                </table>

                <p class="mt-3 text-sm"><span class="font-medium">Alasan:</span> {{ $revision->reason }}</p>
                @if ($revision->decision_note)<p class="mt-1 text-sm text-danger"><span class="font-medium">Catatan Kaprodi:</span> {{ $revision->decision_note }}</p>@endif
                @if ($revision->decided_at)<p class="mt-1 text-xs text-text-muted">Diputuskan {{ $revision->decider?->name }} · {{ $revision->decided_at->translatedFormat('j F Y') }}</p>@endif

                @if ($canDecide && $revision->status === \App\Enums\GradeRevisionStatus::Pending)
                    @if ($rejectingId === $revision->id)
                        <form wire:submit="reject" class="mt-4 space-y-2">
                            <x-field label="Alasan penolakan" name="note" required>
                                <input id="note" type="text" wire:model="note" class="input" autofocus @error('note') aria-invalid="true" @enderror>
                            </x-field>
                            <div class="flex gap-2">
                                <x-button type="submit" size="sm" variant="danger">Tolak revisi</x-button>
                                <x-button size="sm" variant="secondary" wire:click="cancelReject">Batal</x-button>
                            </div>
                        </form>
                    @else
                        <div class="mt-4 flex flex-wrap gap-2">
                            <x-button size="sm" icon="check" wire:click="approve({{ $revision->id }})" wire:loading.attr="disabled" wire:target="approve">Setujui</x-button>
                            <x-button size="sm" variant="secondary" wire:click="startReject({{ $revision->id }})">Tolak</x-button>
                        </div>
                    @endif
                @endif
            </x-card>
        @empty
            <x-card><x-empty-state icon="arrow-path" title="Belum ada pengajuan revisi" :description="$canDecide ? 'Pengajuan dari dosen akan muncul di sini untuk kamu putuskan.' : 'Nilai yang sudah dikunci bisa direvisi lewat tombol Revisi di lembar nilai, dan perlu persetujuan Kaprodi.'" /></x-card>
        @endforelse
    </div>

    @error('decision')<p class="text-sm text-danger" role="alert">{{ $message }}</p>@enderror
</div>
