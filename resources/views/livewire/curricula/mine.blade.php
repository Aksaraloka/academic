<div class="mx-auto max-w-4xl space-y-6">
    <x-page-header title="Kurikulum" subtitle="Mata kuliah di kurikulum prodimu dan kemajuan studimu." />

    @if (! $curriculum)
        <x-card><x-empty-state icon="academic-cap" title="Belum ada kurikulum" description="Kurikulum untuk prodi dan angkatanmu belum diatur oleh BAAK." /></x-card>
    @else
        <div class="grid gap-4 sm:grid-cols-3">
            <x-stat-tile label="SKS kurikulum" :value="$creditsTotal" :note="$creditsRequired.' SKS wajib'" />
            <x-stat-tile label="SKS lulus di kurikulum" :value="$creditsPassed" />
            <x-stat-tile label="Wajib belum lulus" :value="$requiredLeft" note="mata kuliah" />
        </div>
        <p class="text-sm text-text-muted">{{ $curriculum->name }} · {{ $curriculum->batchLabel() }}</p>

        @foreach ($bySemester as $semesterNumber => $rows)
            <x-card :title="'Semester '.$semesterNumber" :padding="false" wire:key="sem-{{ $semesterNumber }}">
                @foreach ($rows as $row)
                    @php $item = $row['item']; @endphp
                    <div class="flex min-h-14 flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0" wire:key="row-{{ $item->id }}">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ $item->subject->code }} · {{ $item->subject->name }}</p>
                            <p class="text-xs text-text-muted">{{ $item->subject->credits }} SKS @if ($item->subject->prerequisites->isNotEmpty()) · Prasyarat: {{ $item->subject->prerequisites->pluck('name')->implode(', ') }}@endif</p>
                        </div>
                        <x-badge :variant="$item->type->variant()">{{ $item->type->label() }}</x-badge>
                        @if ($row['passed'])
                            <x-badge variant="success" icon="check-circle">Lulus · {{ $row['grade']->letter }}</x-badge>
                        @elseif ($row['planned'])
                            <x-badge variant="info">Di KRS semester ini</x-badge>
                        @elseif ($row['grade'])
                            <x-badge variant="danger">Belum lulus · {{ $row['grade']->letter }}</x-badge>
                        @else
                            <x-badge>Belum diambil</x-badge>
                        @endif
                    </div>
                @endforeach
            </x-card>
        @endforeach
    @endif
</div>
