@php
    $letterTone = ['A' => 'success', 'B' => 'success', 'C' => 'info', 'D' => 'warning', 'E' => 'danger'];
    $num = fn (float $value, int $decimals = 2): string => number_format($value, $decimals, ',', '.');
@endphp
<div class="space-y-6">
    <x-page-header title="Transkrip nilai" :subtitle="$student ? $student->name.' · NIM '.$student->nim : 'Rekap seluruh mata kuliah'">
        @if ($transcript)
            <x-slot:actions>
                <x-button :href="route('grades.khs', array_filter(['student' => $studentId, 'child' => $childId]))" wire:navigate variant="secondary" icon="chart-bar">KHS</x-button>
                <x-button :href="route('grades.transcript.pdf', $pdfQuery)" icon="arrow-down-tray" download>Unduh PDF</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    @if (! $student)
        <x-card><x-empty-state icon="identification" title="Data mahasiswa belum tertaut" description="Minta BAAK menautkan akunmu ke data mahasiswa agar transkrip tampil di sini." /></x-card>
    @else
        @if ($children->count() > 1)
            <div class="max-w-xs">
                <label for="anak" class="mb-1 block text-xs font-medium text-text-muted">Anak</label>
                <select id="anak" wire:model.live="childId" class="input">
                    @foreach ($children as $child)<option value="{{ $child->id }}">{{ $child->name }}</option>@endforeach
                </select>
            </div>
        @endif

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <x-stat-tile label="IPK" :value="$transcript['ipk'] === null ? '–' : $num($transcript['ipk'])" tone="text-primary" />
            <x-stat-tile label="SKS lulus" :value="$transcript['credits_passed']" />
            <x-stat-tile label="SKS dihitung" :value="$transcript['credits_counted']" />
            <x-stat-tile label="Program studi" :value="$student->program?->code ?? '–'" :note="$student->program?->label()" />
        </div>

        @if ($transcript['legacy_credits'] > 0)
            <p class="rounded-lg border border-border bg-surface-muted p-3 text-sm text-text-muted" role="status">
                Termasuk nilai awal sebelum sistem ini dipakai: {{ $transcript['legacy_credits'] }} SKS dengan total mutu {{ $num($transcript['legacy_quality_points'], 1) }}.
            </p>
        @endif

        @forelse ($transcript['groups'] as $termId => $rows)
            @php $term = $rows->first()['term']; @endphp
            <x-card :title="$term->name.' · '.$term->semester" :padding="false" wire:key="tr-{{ $termId }}">
                <table class="table-responsive">
                    <thead>
                        <tr><th scope="col">Kode</th><th scope="col">Mata kuliah</th><th scope="col" class="text-right">SKS</th><th scope="col" class="text-right">Nilai</th><th scope="col">Huruf</th><th scope="col" class="text-right">Mutu</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr wire:key="trr-{{ $row['grade']->id }}">
                                <td data-label="Kode" class="tabular-nums text-text-muted">{{ $row['subject']->code }}</td>
                                <td data-label="Mata kuliah" class="font-medium">
                                    {{ $row['subject']->name }}
                                    @if ($row['kind'] === \App\Enums\GradeKind::Remedial)<x-badge variant="info" class="ml-1">Remedial</x-badge>@endif
                                    @if ($row['repeated'])<x-badge class="ml-1">Mengulang</x-badge>@endif
                                </td>
                                <td data-label="SKS" class="tabular-nums sm:text-right">{{ $row['credits'] }}</td>
                                <td data-label="Nilai" class="tabular-nums sm:text-right">{{ $num($row['total'], 1) }}</td>
                                <td data-label="Huruf"><x-badge :variant="$letterTone[$row['letter']]">{{ $row['letter'] }}</x-badge></td>
                                <td data-label="Mutu" class="tabular-nums sm:text-right">{{ $num($row['points'], 1) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>
        @empty
            @if ($transcript['converted']->isEmpty())
                <x-card><x-empty-state icon="document-text" title="Belum ada nilai di transkrip" description="Mata kuliah muncul di sini setelah dosen mengunci nilainya." /></x-card>
            @endif
        @endforelse

        @if ($transcript['converted']->isNotEmpty())
            <x-card title="Hasil konversi SKS (magang, MBKM, dan lainnya)" :padding="false">
                <table class="table-responsive">
                    <thead>
                        <tr><th scope="col">Kode</th><th scope="col">Mata kuliah</th><th scope="col">Sumber</th><th scope="col" class="text-right">SKS</th><th scope="col">Huruf</th><th scope="col" class="text-right">Mutu</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($transcript['converted'] as $row)
                            <tr wire:key="trc-{{ $row['item']->id }}">
                                <td data-label="Kode" class="tabular-nums text-text-muted">{{ $row['subject']->code }}</td>
                                <td data-label="Mata kuliah" class="font-medium">{{ $row['subject']->name }}</td>
                                <td data-label="Sumber" class="text-text-muted">{{ $row['source']->label() }}</td>
                                <td data-label="SKS" class="tabular-nums sm:text-right">{{ $row['credits'] }}</td>
                                <td data-label="Huruf"><x-badge :variant="$letterTone[$row['letter']]">{{ $row['letter'] }}</x-badge></td>
                                <td data-label="Mutu" class="tabular-nums sm:text-right">{{ $num($row['points'], 1) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>
        @endif

        <p class="text-xs text-text-muted">Transkrip memakai hasil terbaik untuk tiap mata kuliah yang diulang atau diremedial. IPK = jumlah (SKS × mutu) ÷ jumlah SKS. SKS lulus adalah mata kuliah dengan nilai D ke atas.</p>
    @endif
</div>
