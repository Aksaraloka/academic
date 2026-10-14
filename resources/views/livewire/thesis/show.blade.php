<div class="space-y-6">
    <x-page-header :title="$thesis->student->name" :subtitle="$thesis->student->nim.' · '.($thesis->student->program?->label() ?? '-')">
        <x-slot:actions>
            <x-button :href="route('thesis.index')" wire:navigate variant="secondary" icon="arrow-left">Tugas akhir</x-button>
            <x-button :href="route('grades.transcript', ['student' => $thesis->student_id])" wire:navigate variant="secondary" icon="document-text">Transkrip</x-button>
        </x-slot:actions>
    </x-page-header>

    @foreach (['decision', 'consent', 'result', 'feedback'] as $key)@error($key)<p class="rounded-lg border border-danger/40 bg-danger/10 p-3 text-sm text-danger" role="alert">{{ $message }}</p>@enderror @endforeach

    <x-card>
        <div class="flex flex-wrap items-center gap-3"><x-badge :variant="$thesis->status->variant()">{{ $thesis->status->label() }}</x-badge>@if ($thesis->final_letter)<x-badge variant="success">Nilai {{ $thesis->final_letter }} ({{ number_format((float) $thesis->final_score, 2, ',', '.') }})</x-badge>@endif</div>
        <h2 class="mt-3 text-lg font-semibold">{{ $thesis->title }}</h2>
        @if ($thesis->abstract)<p class="mt-1 whitespace-pre-line text-sm text-text-muted">{{ $thesis->abstract }}</p>@endif
        @if ($thesis->decision_note)<p class="mt-2 text-sm">Catatan prodi: {{ $thesis->decision_note }}</p>@endif
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
            @foreach ($thesis->supervisors as $supervisor)
                <div class="rounded-lg border border-border p-3 text-sm"><p class="text-text-muted">{{ $supervisor->roleLabel() }}</p><p class="font-medium">{{ $supervisor->teacher->name }}</p></div>
            @endforeach
        </div>
    </x-card>

    @if ($manager && $thesis->status === \App\Enums\ThesisStatus::Proposed)
        <x-card title="Keputusan judul">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Pembimbing utama" name="primaryId" required>
                    <select id="primaryId" wire:model="primaryId" class="input"><option value="">Pilih dosen</option>@foreach ($teachers as $teacher)<option value="{{ $teacher->id }}">{{ $teacher->name }}</option>@endforeach</select>
                </x-field>
                <x-field label="Pembimbing pendamping" name="secondaryId">
                    <select id="secondaryId" wire:model="secondaryId" class="input"><option value="">Tanpa pendamping</option>@foreach ($teachers as $teacher)<option value="{{ $teacher->id }}">{{ $teacher->name }}</option>@endforeach</select>
                </x-field>
                <div class="sm:col-span-2"><x-field label="Catatan" name="note" hint="Wajib bila menolak."><input id="note" type="text" wire:model="note" class="input" @error('note') aria-invalid="true" @enderror></x-field></div>
            </div>
            <div class="mt-4 flex gap-2"><x-button wire:click="approve">Setujui dan tetapkan pembimbing</x-button><x-button variant="secondary" wire:click="reject">Tolak judul</x-button></div>
        </x-card>
    @elseif ($manager && in_array($thesis->status, [\App\Enums\ThesisStatus::Supervised, \App\Enums\ThesisStatus::DefenseRequested], true))
        <x-card title="Ubah pembimbing">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Pembimbing utama" name="primaryId" required><select id="primaryId" wire:model="primaryId" class="input">@foreach ($teachers as $teacher)<option value="{{ $teacher->id }}">{{ $teacher->name }}</option>@endforeach</select></x-field>
                <x-field label="Pembimbing pendamping" name="secondaryId"><select id="secondaryId" wire:model="secondaryId" class="input"><option value="">Tanpa pendamping</option>@foreach ($teachers as $teacher)<option value="{{ $teacher->id }}">{{ $teacher->name }}</option>@endforeach</select></x-field>
            </div>
            <div class="mt-4"><x-button size="sm" variant="secondary" wire:click="changeSupervisors">Simpan pembimbing</x-button></div>
        </x-card>
    @endif

    @if ($readiness)
        <x-card title="Kesiapan sidang">
            <ul class="space-y-2 text-sm">
                <li>Log terverifikasi: <strong>{{ $readiness['verified'] }}</strong> dari minimal {{ $readiness['min_logs'] }}</li>
                <li>SKS lulus: <strong>{{ $readiness['credits'] }}</strong> dari minimal {{ $readiness['min_credits'] }}</li>
                <li>Persetujuan pembimbing utama: <strong>{{ $readiness['consent'] ? 'Sudah' : 'Belum' }}</strong></li>
            </ul>
            @if ($primary && ! $readiness['consent'])<div class="mt-4"><x-button size="sm" wire:click="grantConsent" wire:confirm="Setujui mahasiswa ini maju sidang?" :disabled="! $readiness['logs_ok']">Setujui maju sidang</x-button></div>@endif
        </x-card>
    @endif

    @if ($manager && in_array($thesis->status, [\App\Enums\ThesisStatus::DefenseRequested, \App\Enums\ThesisStatus::DefenseScheduled], true))
        <x-card :title="$scheduledDefense ? 'Ubah jadwal sidang' : 'Jadwalkan sidang'">
            @error('date')<p class="mb-3 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-field label="Tanggal" name="date" required><input id="date" type="date" wire:model="date" class="input"></x-field>
                <x-field label="Mulai" name="starts_at" required><input id="starts_at" type="time" wire:model="starts_at" class="input"></x-field>
                <x-field label="Selesai" name="ends_at" required><input id="ends_at" type="time" wire:model="ends_at" class="input"></x-field>
                <x-field label="Ruang" name="roomId"><select id="roomId" wire:model="roomId" class="input"><option value="">Belum ditentukan</option>@foreach ($rooms as $room)<option value="{{ $room->id }}">{{ $room->name }}</option>@endforeach</select></x-field>
                <x-field label="Ketua sidang" name="chairId" required><select id="chairId" wire:model="chairId" class="input"><option value="">Pilih</option>@foreach ($teachers as $teacher)<option value="{{ $teacher->id }}">{{ $teacher->name }}</option>@endforeach</select></x-field>
                <x-field label="Penguji" name="examinerId" required><select id="examinerId" wire:model="examinerId" class="input"><option value="">Pilih</option>@foreach ($teachers as $teacher)<option value="{{ $teacher->id }}">{{ $teacher->name }}</option>@endforeach</select></x-field>
            </div>
            <div class="mt-4"><x-button wire:click="scheduleDefense" wire:loading.attr="disabled" wire:target="scheduleDefense">Simpan jadwal</x-button></div>
        </x-card>
    @endif

    @if ($canRecord && $scheduledDefense)
        <x-card title="Hasil sidang">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-field label="Hasil" name="result" required><select id="result" wire:model="result" class="input">@foreach ($results as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach</select></x-field>
                <x-field label="Nilai (0–100)" name="score" required><input id="score" type="number" min="0" max="100" step="0.01" wire:model="score" class="input text-right tabular-nums" inputmode="decimal" @error('score') aria-invalid="true" @enderror></x-field>
                <x-field label="Batas revisi" name="revisionDeadline"><input id="revisionDeadline" type="date" wire:model="revisionDeadline" class="input"></x-field>
                <div class="sm:col-span-2 lg:col-span-4"><x-field label="Catatan penguji" name="resultNotes"><textarea id="resultNotes" wire:model="resultNotes" rows="2" class="input"></textarea></x-field></div>
            </div>
            <div class="mt-4"><x-button wire:click="recordResult" wire:confirm="Simpan hasil sidang? Tindakan ini mengubah status tugas akhir.">Simpan hasil</x-button></div>
        </x-card>
    @endif

    @foreach ($thesis->defenses as $defense)
        <x-card :title="'Sidang '.$defense->scheduled_on->translatedFormat('j F Y')" wire:key="def-{{ $defense->id }}">
            <p class="text-sm">{{ $defense->timeRange() }} · {{ $defense->room?->name ?? 'Ruang belum ditentukan' }} · Ketua {{ $defense->chair?->name ?? '-' }} · Penguji {{ $defense->examiner?->name ?? '-' }}</p>
            @if ($defense->result)
                <div class="mt-2 flex flex-wrap items-center gap-2"><x-badge :variant="$defense->result->variant()">{{ $defense->result->label() }}</x-badge><span class="text-sm tabular-nums">Nilai {{ number_format((float) $defense->score, 2, ',', '.') }}</span></div>
                @if ($defense->notes)<p class="mt-1 text-sm">{{ $defense->notes }}</p>@endif
            @else
                <x-badge class="mt-2" variant="primary">Terjadwal</x-badge>
            @endif
        </x-card>
    @endforeach

    <x-card title="Log bimbingan" :padding="false">
        @forelse ($thesis->logs as $log)
            <div class="border-t border-border px-4 py-3 first:border-t-0" wire:key="log-{{ $log->id }}">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="flex-1 text-sm font-medium">{{ $log->topic }}</p>
                    <span class="text-xs text-text-muted">{{ $log->held_on->translatedFormat('j F Y') }} · {{ $log->teacher->name }}</span>
                    <x-badge :variant="$log->status->variant()">{{ $log->status->label() }}</x-badge>
                </div>
                <p class="mt-1 whitespace-pre-line text-sm">{{ $log->summary }}</p>
                @if ($log->next_steps)<p class="mt-1 text-xs text-text-muted">Tindak lanjut: {{ $log->next_steps }}</p>@endif
                @if ($log->feedback)<p class="mt-1 text-xs text-text-muted">Catatan pembimbing: {{ $log->feedback }}</p>@endif
                @if ($log->status === \App\Enums\GuidanceStatus::Pending && $log->teacher_id === $myTeacherId)
                    <div class="mt-2 flex gap-2"><x-button size="sm" wire:click="verifyLog({{ $log->id }})">Verifikasi</x-button><x-button size="sm" variant="secondary" wire:click="startRevise({{ $log->id }})">Minta perbaikan</x-button></div>
                @endif
                @if ($revisingLogId === $log->id)
                    <form wire:submit="reviseLog" class="mt-2 space-y-2">
                        <x-field label="Catatan perbaikan" name="feedback" required><input id="feedback" type="text" wire:model="feedback" class="input" @error('feedback') aria-invalid="true" @enderror></x-field>
                        <x-button type="submit" size="sm">Kirim</x-button>
                    </form>
                @endif
            </div>
        @empty
            <p class="px-4 py-4 text-sm text-text-muted">Belum ada log bimbingan.</p>
        @endforelse
    </x-card>
</div>
