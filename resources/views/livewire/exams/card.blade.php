<div class="space-y-6">
    <x-page-header title="Kartu ujian" :subtitle="$student ? $student->name.' · NIM '.$student->nim : 'Jadwal dan syarat mengikuti ujian'">
        <x-slot:actions>
            @if ($children->count() > 1)
                <select wire:model.live="childId" class="input" aria-label="Pilih anak">
                    @foreach ($children as $child)<option value="{{ $child->id }}" @selected($child->id === $student?->id)>{{ $child->name }}</option>@endforeach
                </select>
            @endif
            @if ($issued)
                <x-button :href="route('exams.card.pdf', $pdfQuery)" icon="arrow-down-tray" download>Unduh PDF</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if (! $student || ! $term)
        <x-card><x-empty-state icon="identification" title="Kartu ujian belum tersedia" description="Kartu ujian tersedia untuk mahasiswa pada semester aktif." /></x-card>
    @else
        <div class="grid grid-cols-2 gap-1 rounded-lg bg-surface-muted p-1" role="tablist" aria-label="Jenis ujian">
            @foreach ($types as $option)
                <button type="button" role="tab" aria-selected="{{ $examType === $option ? 'true' : 'false' }}" wire:click="$set('type', '{{ $option->value }}')"
                        class="flex min-h-11 items-center justify-center rounded-md text-sm font-semibold transition-colors {{ $examType === $option ? 'bg-surface text-primary shadow-sm' : 'text-text-muted hover:text-text' }}">{{ $option->label() }}</button>
            @endforeach
        </div>

        <x-card>
            <div class="flex flex-wrap items-center gap-3">
                <x-badge :variant="$issued ? 'success' : 'warning'" :icon="$issued ? 'check-circle' : 'lock-closed'">{{ $issued ? 'Kartu ujian terbit' : 'Kartu ujian belum terbit' }}</x-badge>
                <span class="text-sm text-text-muted">{{ $examType->fullLabel() }} · {{ $term->name }} {{ $term->semester }}</span>
            </div>
            <p class="mt-2 text-sm text-text-muted">Syarat: kehadiran minimal {{ $minPercent }}% di tiap mata kuliah, status akademik aktif, dan tagihan semester lunas bila kampus mewajibkannya.</p>
            @if ($blocker)<p class="mt-2 text-sm text-danger" role="alert">{{ $blocker }}</p>@endif
        </x-card>

        <x-card title="Mata kuliah dan jadwal ujian" :padding="false">
            @forelse ($rows as $row)
                <div class="flex min-h-14 flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0" wire:key="row-{{ $row['assignment']->id }}">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ $row['assignment']->subject->code }} · {{ $row['assignment']->subject->name }}</p>
                        <p class="truncate text-xs text-text-muted">
                            @if ($row['exam'])
                                {{ $row['exam']->date->translatedFormat('l, j F Y') }} · {{ $row['exam']->timeRange() }} · {{ $row['exam']->room?->name ?? 'Ruang belum ditentukan' }}
                            @else
                                Jadwal belum diumumkan
                            @endif
                        </p>
                        <p class="truncate text-xs text-text-muted">Kehadiran {{ $row['percent'] !== null ? $row['percent'].'% ('.$row['attended'].'/'.$row['held'].')' : 'belum ada presensi' }}</p>
                    </div>
                    @if ($row['eligible'])
                        <x-badge variant="success">Berhak ikut</x-badge>
                    @else
                        <x-badge variant="danger">Tidak berhak</x-badge>
                    @endif
                    @unless ($row['eligible'])<p class="w-full text-xs text-danger">{{ $row['reason'] }} Hubungi dosen pengampu atau BAAK bila ada kekeliruan data presensi.</p>@endunless
                </div>
            @empty
                <x-empty-state icon="clipboard-document-list" title="Belum ada mata kuliah" description="Mata kuliahmu muncul setelah KRS disetujui atau kelas ditetapkan." />
            @endforelse
        </x-card>
    @endif
</div>
