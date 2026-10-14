<div class="space-y-6">
    <x-page-header title="Hasil evaluasi dosen" :subtitle="$isTeacher ? 'Rekap evaluasi mahasiswa untuk kelas yang kamu ajar.' : 'Rekap EDOM per kelas mata kuliah.'">
        @if (! $isTeacher)
            <x-slot:actions><x-button :href="route('evaluations.periods')" wire:navigate variant="secondary" icon="cog-6-tooth">Periode</x-button></x-slot:actions>
        @endif
    </x-page-header>

    @if (! $term)
        <x-card><x-empty-state icon="calendar-days" title="Belum ada semester" description="Admin perlu menambahkan semester akademik lebih dulu." /></x-card>
    @else
        <div class="max-w-xs">
            <x-field label="Semester" name="termId">
                <select id="termId" wire:model.live="termId" class="input">
                    @foreach ($terms as $option)<option value="{{ $option->id }}" @selected($option->id === $term->id)>{{ $option->name }} · {{ $option->semester }}{{ $option->is_active ? ' (aktif)' : '' }}</option>@endforeach
                </select>
            </x-field>
        </div>

        @if (! $period)
            <x-card><x-empty-state icon="chart-bar" title="Belum ada periode evaluasi" description="Semester ini belum punya periode evaluasi dosen yang diterbitkan." /></x-card>
        @else
            <p class="text-sm text-text-muted">Rata-rata dan komentar tampil setelah minimal {{ $minResponses }} mahasiswa mengisi, supaya jawaban tetap anonim.</p>

            @forelse ($rows as $row)
                @php $summary = $row['summary']; $assignment = $row['assignment']; @endphp
                <x-card :title="$assignment->subject->name.' · '.$assignment->teacher->name" wire:key="res-{{ $assignment->id }}">
                    <p class="mb-3 text-xs text-text-muted">Kelas {{ $assignment->classLabel() }} · {{ $summary['responses'] }} responden</p>
                    @if (! $summary['visible'])
                        <p class="text-sm text-text-muted">Belum cukup responden untuk menampilkan rekap.</p>
                    @else
                        <p class="mb-3 text-sm">Rata-rata keseluruhan: <strong class="tabular-nums">{{ number_format($summary['average'], 2, ',', '.') }}</strong> dari 5</p>
                        <div class="space-y-2">
                            @foreach ($summary['ratings'] as $rating)
                                <div>
                                    <div class="flex justify-between gap-3 text-sm"><span>{{ $rating['text'] }}</span><span class="font-semibold tabular-nums">{{ number_format($rating['average'], 2, ',', '.') }}</span></div>
                                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-surface-muted"><div class="h-full rounded-full bg-primary" style="width: {{ $rating['average'] / 5 * 100 }}%"></div></div>
                                </div>
                            @endforeach
                        </div>
                        @if ($summary['comments']->isNotEmpty())
                            <p class="mb-1 mt-4 text-sm font-semibold">Komentar</p>
                            <ul class="list-disc space-y-1 pl-5 text-sm">@foreach ($summary['comments'] as $comment)<li>{{ $comment }}</li>@endforeach</ul>
                        @endif
                    @endif
                </x-card>
            @empty
                <x-card><x-empty-state icon="chart-bar" title="Belum ada evaluasi masuk" description="Rekap muncul setelah mahasiswa mengisi evaluasi." /></x-card>
            @endforelse
        @endif
    @endif
</div>
