@php
    $letterTone = ['A' => 'success', 'B' => 'success', 'C' => 'info', 'D' => 'warning', 'E' => 'danger'];
    $num = fn (float $value, int $decimals = 2): string => number_format($value, $decimals, ',', '.');
@endphp
<div class="space-y-6">
    <x-page-header title="Kartu Hasil Studi (KHS)" :subtitle="$student ? $student->name.' · NIM '.$student->nim : 'Hasil studi per semester'">
        @if ($khs)
            <x-slot:actions>
                <x-button :href="route('grades.transcript', array_filter(['mahasiswa' => $studentId, 'anak' => $childId]))" wire:navigate variant="secondary" icon="document-text">Transkrip</x-button>
                <x-button :href="route('grades.khs.pdf', $pdfQuery)" icon="arrow-down-tray" download>Unduh PDF</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    @if (! $student)
        <x-card><x-empty-state icon="identification" title="Data mahasiswa belum tertaut" description="Minta BAAK menautkan akunmu ke data mahasiswa agar KHS tampil di sini." /></x-card>
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:max-w-2xl">
            <div>
                <label for="semester" class="mb-1 block text-xs font-medium text-text-muted">Semester</label>
                <select id="semester" wire:model.live="termId" class="input" @disabled($terms->isEmpty())>
                    @forelse ($terms as $option)
                        <option value="{{ $option->id }}" @selected($term?->id === $option->id)>{{ $option->name }} · {{ $option->semester }}{{ $option->is_active ? ' (aktif)' : '' }}</option>
                    @empty
                        <option>Belum ada nilai</option>
                    @endforelse
                </select>
            </div>
            @if ($children->count() > 1)
                <div>
                    <label for="anak" class="mb-1 block text-xs font-medium text-text-muted">Anak</label>
                    <select id="anak" wire:model.live="childId" class="input">
                        @foreach ($children as $child)<option value="{{ $child->id }}">{{ $child->name }}</option>@endforeach
                    </select>
                </div>
            @endif
        </div>

        @if (! $khs)
            <x-card><x-empty-state icon="chart-bar" title="Belum ada nilai" description="Nilai tampil di sini setelah dosen mengunci nilai kelasnya." /></x-card>
        @else
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <x-stat-tile label="IPS semester ini" :value="$khs['ips'] === null ? '–' : $num($khs['ips'])" tone="text-primary" :note="$khs['term']->name.' '.$khs['term']->semester" />
                <x-stat-tile label="SKS semester ini" :value="$khs['credits']" />
                <x-stat-tile label="IPK kumulatif" :value="$khs['cumulative']['ipk'] === null ? '–' : $num($khs['cumulative']['ipk'])" tone="text-primary" />
                <x-stat-tile label="SKS lulus kumulatif" :value="$khs['cumulative']['credits_passed']" />
            </div>

            <x-card :padding="false">
                <table class="table-responsive">
                    <thead>
                        <tr>
                            <th scope="col">Kode</th><th scope="col">Mata kuliah</th><th scope="col" class="text-right">SKS</th>
                            <th scope="col" class="text-right">Nilai</th><th scope="col">Huruf</th><th scope="col" class="text-right">Mutu</th><th scope="col" class="text-right">SKS × Mutu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($khs['rows'] as $row)
                            <tr wire:key="khs-{{ $row['grade']->id }}">
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
                                <td data-label="SKS × Mutu" class="tabular-nums sm:text-right">{{ $num($row['mutu'], 1) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-empty-state icon="chart-bar" title="Belum ada nilai yang dirilis" description="Nilai semester ini tampil setelah dosen mengunci nilai kelasnya." /></td></tr>
                        @endforelse
                    </tbody>
                    @if ($khs['rows']->isNotEmpty())
                        <tfoot>
                            <tr class="border-t border-border bg-surface-muted font-semibold">
                                <td colspan="2" class="px-4 py-3">Jumlah semester ini</td>
                                <td class="px-4 py-3 tabular-nums sm:text-right">{{ $khs['credits'] }}</td>
                                <td colspan="2" class="px-4 py-3 sm:text-right">IPS</td>
                                <td class="px-4 py-3 tabular-nums text-primary sm:text-right">{{ $khs['ips'] === null ? '–' : $num($khs['ips']) }}</td>
                                <td class="px-4 py-3 tabular-nums sm:text-right">{{ $num($khs['quality_points'], 1) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </x-card>

            @if ($khs['pending']->isNotEmpty())
                <p class="rounded-lg border border-border bg-surface-muted p-3 text-sm text-text-muted" role="status">
                    Belum dirilis dosen: {{ $khs['pending']->map(fn ($item) => $item['subject']->name)->implode(', ') }}. Nilainya masuk IPS setelah dikunci.
                </p>
            @endif

            <p class="text-xs text-text-muted">IPS = jumlah (SKS × mutu) ÷ jumlah SKS semester ini. Mutu: A = 4, B = 3, C = 2, D = 1, E = 0. Untuk mata kuliah yang diulang atau diremedial, hasil terbaik yang dipakai.</p>
        @endif
    @endif
</div>
