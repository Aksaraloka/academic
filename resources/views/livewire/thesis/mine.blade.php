<div class="space-y-6">
    <x-page-header title="Tugas akhir" subtitle="Pengajuan judul, pembimbing, log bimbingan, dan jadwal sidang." />

    @if (! $thesis)
        @if ($rejected)
            <div class="rounded-lg border border-danger/40 bg-danger/10 p-3 text-sm" role="alert">
                <p class="font-semibold">Judul sebelumnya ditolak</p>
                <p class="mt-1">{{ $rejected->title }}</p>
                @if ($rejected->decision_note)<p class="mt-1">Alasan: {{ $rejected->decision_note }}</p>@endif
            </div>
        @endif

        <form wire:submit="propose">
            <x-card title="Ajukan judul">
                <p class="mb-3 text-sm {{ $credits >= $minCredits ? 'text-text-muted' : 'text-warning' }}">Syarat: SKS lulus minimal {{ $minCredits }} (kamu: {{ $credits }}) dan berstatus aktif.</p>
                <div class="grid gap-4">
                    <x-field label="Judul tugas akhir" name="title" required><input id="title" type="text" wire:model="title" class="input" @error('title') aria-invalid="true" @enderror></x-field>
                    <x-field label="Latar belakang singkat" name="abstract" hint="Opsional. Jelaskan masalah dan tujuan penelitian."><textarea id="abstract" wire:model="abstract" rows="4" class="input"></textarea></x-field>
                </div>
                <div class="mt-4"><x-button type="submit" wire:loading.attr="disabled" wire:target="propose">Ajukan judul</x-button></div>
            </x-card>
        </form>
    @else
        <x-card>
            <div class="flex flex-wrap items-center gap-3">
                <x-badge :variant="$thesis->status->variant()">{{ $thesis->status->label() }}</x-badge>
                @if ($thesis->final_letter)<x-badge variant="success">Nilai {{ $thesis->final_letter }} ({{ number_format((float) $thesis->final_score, 2, ',', '.') }})</x-badge>@endif
            </div>
            <h2 class="mt-3 text-lg font-semibold">{{ $thesis->title }}</h2>
            @if ($thesis->abstract)<p class="mt-1 whitespace-pre-line text-sm text-text-muted">{{ $thesis->abstract }}</p>@endif
            @if ($thesis->decision_note)<p class="mt-2 text-sm">Catatan prodi: {{ $thesis->decision_note }}</p>@endif
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                @forelse ($thesis->supervisors as $supervisor)
                    <div class="rounded-lg border border-border p-3 text-sm"><p class="text-text-muted">{{ $supervisor->roleLabel() }}</p><p class="font-medium">{{ $supervisor->teacher->name }}</p></div>
                @empty
                    <p class="text-sm text-text-muted sm:col-span-2">Pembimbing akan ditetapkan setelah judul disetujui.</p>
                @endforelse
            </div>
        </x-card>

        @error('defense')<p class="rounded-lg border border-danger/40 bg-danger/10 p-3 text-sm text-danger" role="alert">{{ $message }}</p>@enderror

        @if ($readiness)
            <x-card title="Persiapan sidang">
                <ul class="space-y-2 text-sm">
                    <li class="flex items-center gap-2"><x-badge :variant="$readiness['logs_ok'] ? 'success' : 'warning'">{{ $readiness['logs_ok'] ? 'Terpenuhi' : 'Belum' }}</x-badge> Log bimbingan terverifikasi {{ $readiness['verified'] }} dari minimal {{ $readiness['min_logs'] }}</li>
                    <li class="flex items-center gap-2"><x-badge :variant="$readiness['credits_ok'] ? 'success' : 'warning'">{{ $readiness['credits_ok'] ? 'Terpenuhi' : 'Belum' }}</x-badge> SKS lulus {{ $readiness['credits'] }} dari minimal {{ $readiness['min_credits'] }}</li>
                    <li class="flex items-center gap-2"><x-badge :variant="$readiness['consent'] ? 'success' : 'warning'">{{ $readiness['consent'] ? 'Terpenuhi' : 'Belum' }}</x-badge> Persetujuan pembimbing utama untuk maju sidang</li>
                </ul>
                <div class="mt-4"><x-button wire:click="requestDefense" wire:confirm="Ajukan sidang tugas akhir?" :disabled="! $readiness['ready']">Ajukan sidang</x-button></div>
            </x-card>
        @endif

        @foreach ($thesis->defenses as $defense)
            <x-card :title="'Sidang '.$defense->scheduled_on->translatedFormat('j F Y')" wire:key="def-{{ $defense->id }}">
                <p class="text-sm">{{ $defense->timeRange() }} · {{ $defense->room?->name ?? 'Ruang belum ditentukan' }}</p>
                <p class="mt-1 text-sm text-text-muted">Ketua: {{ $defense->chair?->name ?? '-' }} · Penguji: {{ $defense->examiner?->name ?? '-' }}</p>
                @if ($defense->result)
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <x-badge :variant="$defense->result->variant()">{{ $defense->result->label() }}</x-badge>
                        <span class="text-sm tabular-nums">Nilai {{ number_format((float) $defense->score, 2, ',', '.') }}</span>
                        @if ($defense->revision_deadline)<span class="text-xs text-text-muted">Revisi paling lambat {{ $defense->revision_deadline->translatedFormat('j F Y') }}</span>@endif
                    </div>
                    @if ($defense->notes)<p class="mt-2 text-sm">{{ $defense->notes }}</p>@endif
                @else
                    <x-badge class="mt-2" variant="primary">Terjadwal</x-badge>
                @endif
            </x-card>
        @endforeach

        @if ($thesis->status === \App\Enums\ThesisStatus::Supervised)
            <form wire:submit="addLog">
                <x-card title="Catat bimbingan">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field label="Pembimbing" name="teacherId" required>
                            <select id="teacherId" wire:model="teacherId" class="input">
                                <option value="">Pilih pembimbing</option>
                                @foreach ($thesis->supervisors as $supervisor)<option value="{{ $supervisor->teacher_id }}">{{ $supervisor->teacher->name }} ({{ $supervisor->role === 'primary' ? 'utama' : 'pendamping' }})</option>@endforeach
                            </select>
                        </x-field>
                        <x-field label="Tanggal bimbingan" name="held_on" required><input id="held_on" type="date" wire:model="held_on" class="input" max="{{ today()->toDateString() }}"></x-field>
                        <div class="sm:col-span-2"><x-field label="Topik" name="topic" required><input id="topic" type="text" wire:model="topic" class="input" placeholder="Mis. Revisi Bab 3 metodologi" @error('topic') aria-invalid="true" @enderror></x-field></div>
                        <div class="sm:col-span-2"><x-field label="Ringkasan bimbingan" name="summary" required><textarea id="summary" wire:model="summary" rows="3" class="input" @error('summary') aria-invalid="true" @enderror></textarea></x-field></div>
                        <div class="sm:col-span-2"><x-field label="Tindak lanjut" name="next_steps"><input id="next_steps" type="text" wire:model="next_steps" class="input"></x-field></div>
                    </div>
                    <div class="mt-4"><x-button type="submit" wire:loading.attr="disabled" wire:target="addLog">Kirim log</x-button></div>
                </x-card>
            </form>
        @endif

        <x-card title="Riwayat bimbingan" :padding="false">
            @forelse ($thesis->logs as $log)
                <div class="border-t border-border px-4 py-3 first:border-t-0" wire:key="log-{{ $log->id }}">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="flex-1 text-sm font-medium">{{ $log->topic }}</p>
                        <span class="text-xs text-text-muted">{{ $log->held_on->translatedFormat('j F Y') }} · {{ $log->teacher->name }}</span>
                        <x-badge :variant="$log->status->variant()">{{ $log->status->label() }}</x-badge>
                    </div>
                    <p class="mt-1 whitespace-pre-line text-sm">{{ $log->summary }}</p>
                    @if ($log->next_steps)<p class="mt-1 text-xs text-text-muted">Tindak lanjut: {{ $log->next_steps }}</p>@endif
                    @if ($log->feedback)<p class="mt-1 text-xs {{ $log->status === \App\Enums\GuidanceStatus::Revise ? 'text-danger' : 'text-text-muted' }}">Catatan pembimbing: {{ $log->feedback }}</p>@endif
                </div>
            @empty
                <p class="px-4 py-4 text-sm text-text-muted">Belum ada log bimbingan.</p>
            @endforelse
        </x-card>
    @endif
</div>
