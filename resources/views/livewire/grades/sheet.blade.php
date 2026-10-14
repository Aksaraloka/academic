@php
    $letterTone = ['A' => 'success', 'B' => 'success', 'C' => 'info', 'D' => 'warning', 'E' => 'danger'];
    $fmt = fn (?float $value): string => $value === null ? '–' : rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
@endphp
<div class="space-y-6">
    <x-page-header :title="$assignment->subject->name" :subtitle="$assignment->subject->code.' · Kelas '.$assignment->studyGroup->name.' · '.$assignment->academicTerm->name.' '.$assignment->academicTerm->semester.' · '.$assignment->teacher->name">
        <x-slot:actions>
            <x-button :href="route($isOwner ? 'grades.input.index' : 'grades.completeness')" wire:navigate variant="secondary" icon="arrow-left">Kembali</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-wrap items-center gap-2" role="status">
        @if ($locked)
            <x-badge variant="success" icon="lock-closed">Nilai dikunci {{ $assignment->grades_locked_at->translatedFormat('j F Y') }}</x-badge>
        @else
            <x-badge variant="warning" icon="pencil-square">Draf</x-badge>
        @endif
        @if ($deadline)
            <span class="text-sm {{ ! $locked && today()->gt($deadline) ? 'font-semibold text-danger' : 'text-text-muted' }}">Batas input {{ $deadline->translatedFormat('j F Y') }}</span>
        @endif
    </div>

    @error('input')<p class="rounded-lg border border-danger bg-danger/15 p-3 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
    @error('lock')<p class="rounded-lg border border-danger bg-danger/15 p-3 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
    @if ($isOwner && $blockReason && ! $locked)
        <p class="rounded-lg border border-warning bg-warning/15 p-3 text-sm text-warning" role="status">{{ $blockReason }}</p>
    @elseif (! $isOwner)
        <p class="rounded-lg border border-border bg-surface-muted p-3 text-sm text-text-muted" role="status">Kamu melihat lembar nilai ini hanya untuk dibaca.</p>
    @endif

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-stat-tile label="Mahasiswa" :value="$stats['students']" />
        <x-stat-tile label="Nilai lengkap" :value="$stats['complete']" :note="$stats['complete'] < $stats['students'] ? 'Masih ada yang kosong' : 'Semua lengkap'" :tone="$stats['complete'] < $stats['students'] ? 'text-warning' : 'text-success'" />
        <x-stat-tile label="Rata-rata kelas" :value="$stats['average'] === null ? '–' : number_format($stats['average'], 1, ',', '.')" />
        <div class="rounded-card border border-border bg-surface p-4">
            <p class="text-xs font-medium text-text-muted">Sebaran huruf mutu</p>
            <p class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-sm font-semibold tabular-nums">
                @foreach ($stats['letters'] as $letter => $count)<span>{{ $letter }}: {{ $count }}</span>@endforeach
            </p>
        </div>
    </div>

    <x-card title="Bobot penilaian">
        <form wire:submit="saveWeights" class="space-y-3">
            <div class="grid gap-3 sm:grid-cols-4">
                @foreach ($components as $key => $label)
                    <x-field :label="$label.' (%)'" name="weights.{{ $key }}">
                        <input id="weights.{{ $key }}" type="number" min="0" max="100" wire:model.live="weights.{{ $key }}" class="input text-right tabular-nums" inputmode="numeric" @disabled(! $canEdit) @error('weights.'.$key) aria-invalid="true" @enderror>
                    </x-field>
                @endforeach
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <p class="text-sm font-semibold tabular-nums {{ $weightSum === 100 ? 'text-success' : 'text-danger' }}" role="status">Jumlah bobot: {{ $weightSum }}%</p>
                @error('weights')<p class="text-sm text-danger" role="alert">{{ $message }}</p>@enderror
                @if ($canEdit)<x-button type="submit" size="sm" variant="secondary" wire:loading.attr="disabled" wire:target="saveWeights">Simpan bobot</x-button>@endif
            </div>
            <p class="text-xs text-text-muted">Kehadiran dihitung otomatis dari presensi yang kamu catat. Huruf mutu: A ≥ {{ auth()->user()->tenant->setting('grade_scale')['A'] }}, B ≥ {{ auth()->user()->tenant->setting('grade_scale')['B'] }}, C ≥ {{ auth()->user()->tenant->setting('grade_scale')['C'] }}, D ≥ {{ auth()->user()->tenant->setting('grade_scale')['D'] }}, di bawahnya E.</p>
        </form>
    </x-card>

    <x-card :padding="false">
        <table class="table-responsive">
            <thead>
                <tr>
                    <th scope="col">Mahasiswa</th>
                    <th scope="col" class="text-right">Tugas</th>
                    <th scope="col" class="text-right">UTS</th>
                    <th scope="col" class="text-right">UAS</th>
                    <th scope="col" class="text-right">Kehadiran</th>
                    <th scope="col" class="text-right">Nilai akhir</th>
                    <th scope="col">Huruf</th>
                    <th scope="col"><span class="sr-only">Aksi</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php $student = $row['student']; $values = $row['values']; @endphp
                    <tr wire:key="row-{{ $student->id }}">
                        <td data-label="Mahasiswa">
                            <span class="font-medium">{{ $student->name }}</span>
                            <span class="block text-xs tabular-nums text-text-muted">{{ $student->nim }}</span>
                        </td>
                        @foreach (['assignment' => 'tugas', 'midterm' => 'UTS', 'final' => 'UAS'] as $field => $label)
                            <td data-label="{{ $label === 'tugas' ? 'Tugas' : $label }}" class="sm:text-right">
                                @if ($canEdit)
                                    <input type="number" step="0.01" min="0" max="100" inputmode="decimal" wire:model.blur="scores.{{ $student->id }}.{{ $field }}"
                                        class="input !min-h-10 w-24 text-right tabular-nums" aria-label="Nilai {{ $label }} {{ $student->name }}" @error('scores.'.$student->id.'.'.$field) aria-invalid="true" @enderror>
                                @else
                                    <span class="tabular-nums">{{ $fmt($values[$field]) }}</span>
                                @endif
                            </td>
                        @endforeach
                        <td data-label="Kehadiran" class="tabular-nums sm:text-right">{{ $fmt($values['attendance']) }}</td>
                        <td data-label="Nilai akhir" class="font-semibold tabular-nums sm:text-right">{{ $fmt($values['total']) }}</td>
                        <td data-label="Huruf">
                            @if ($values['letter'])<x-badge :variant="$letterTone[$values['letter']]">{{ $values['letter'] }}</x-badge>@else<span class="text-text-muted">–</span>@endif
                            @if ($row['remedial'])<x-badge variant="info" class="ml-1">Remedial {{ $row['remedial']->letter }}</x-badge>@endif
                        </td>
                        <td data-label="Aksi" class="sm:text-right">
                            @if ($locked && $isOwner && $row['regular'])
                                @if ($row['pending'])
                                    <x-badge variant="info" icon="clock">Revisi menunggu</x-badge>
                                @else
                                    <x-button size="sm" variant="ghost" wire:click="startRevision({{ $row['regular']->id }})" icon="arrow-path">Revisi</x-button>
                                @endif
                            @endif
                        </td>
                    </tr>
                    @if ($row['canRemedial'])
                        <tr wire:key="remedial-{{ $student->id }}" class="bg-surface-muted">
                            <td colspan="8" class="!px-4">
                                <form wire:submit="saveRemedial({{ $student->id }})" class="flex flex-wrap items-center gap-3">
                                    <label for="remedial-{{ $student->id }}" class="text-sm font-medium">Nilai remedial {{ $student->name }}</label>
                                    <input id="remedial-{{ $student->id }}" type="number" step="0.01" min="0" max="100" inputmode="decimal" wire:model="remedial.{{ $student->id }}" class="input !min-h-10 w-28 text-right tabular-nums">
                                    <x-button type="submit" size="sm" variant="secondary">Simpan remedial</x-button>
                                    <span class="text-xs text-text-muted">Hasil terbaik dipakai di KHS dan transkrip.</span>
                                </form>
                                @error('remedial')<p class="mt-1 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="8"><x-empty-state icon="users" title="Belum ada mahasiswa" description="Tempatkan mahasiswa di kelas ini lebih dulu." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    @if ($canEdit)
        <div class="flex flex-wrap items-center gap-3">
            <x-button wire:click="save" variant="secondary" icon="check" wire:loading.attr="disabled" wire:target="save,lock">Simpan draf</x-button>
            <x-button wire:click="lock" icon="lock-closed" wire:loading.attr="disabled" wire:target="save,lock"
                wire:confirm="Kunci nilai kelas ini? Setelah dikunci, nilai tampil di KHS mahasiswa dan perubahan harus lewat revisi yang disetujui Kaprodi.">Kunci nilai</x-button>
            <p class="text-sm text-text-muted">Angka tersimpan saat kamu keluar dari kolom. Gunakan Simpan draf untuk memastikan.</p>
        </div>
        @error('scores.*')<p class="text-sm text-danger" role="alert">{{ $message }}</p>@enderror

        <x-card title="Tambah mahasiswa mengulang">
            <form wire:submit="addRepeater" class="flex flex-wrap items-end gap-3">
                <div class="w-full max-w-xs">
                    <x-field label="NIM mahasiswa" name="nim" hint="Untuk mahasiswa yang mengulang mata kuliah ini bersama kelas lain.">
                        <input id="nim" type="text" wire:model="nim" class="input" inputmode="numeric" @error('nim') aria-invalid="true" @enderror>
                    </x-field>
                </div>
                <x-button type="submit" variant="secondary" icon="plus">Tambahkan</x-button>
            </form>
        </x-card>
    @endif

    @if ($revisingGrade)
        <x-card :title="'Ajukan revisi nilai · '.$revisingGrade->student->name">
            <form wire:submit="submitRevision" class="space-y-4">
                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach (['assignment' => 'Tugas', 'midterm' => 'UTS', 'final' => 'UAS'] as $field => $label)
                        <x-field :label="$label" name="revision.{{ $field }}">
                            <input id="revision.{{ $field }}" type="number" step="0.01" min="0" max="100" inputmode="decimal" wire:model="revision.{{ $field }}" class="input text-right tabular-nums" @error('revision.'.$field) aria-invalid="true" @enderror>
                        </x-field>
                    @endforeach
                </div>
                <x-field label="Alasan revisi" name="revisionReason" required hint="Jelaskan kesalahan yang dikoreksi (minimal 10 karakter). Kaprodi akan memutuskan.">
                    <textarea id="revisionReason" wire:model="revisionReason" rows="2" class="input"></textarea>
                </x-field>
                @error('revision')<p class="text-sm text-danger" role="alert">{{ $message }}</p>@enderror
                <div class="flex flex-wrap gap-2">
                    <x-button type="submit" icon="paper-airplane" wire:loading.attr="disabled" wire:target="submitRevision">Kirim pengajuan</x-button>
                    <x-button variant="secondary" wire:click="cancelRevision">Batal</x-button>
                </div>
            </form>
        </x-card>
    @endif
</div>
