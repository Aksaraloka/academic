<div class="space-y-6">
    <x-page-header title="Izin dan sakit" subtitle="Catatan izin mahasiswa dan pengajuan izin dosen.">
        @if ($recordable->isNotEmpty() || $canRequestOwn)
            <x-slot:actions>
                <x-button wire:click="$toggle('showForm')" icon="plus">Catat izin</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    @if ($showForm)
        <form wire:submit="save">
            <x-card title="Izin baru">
                <div class="grid gap-4 sm:grid-cols-2">
                    @if ($recordable->isNotEmpty())
                        <div class="sm:col-span-2">
                            <x-field label="Mahasiswa" name="studentId" :hint="$canRequestOwn ? 'Kosongkan bila ini izin untuk dirimu sendiri.' : null">
                                <select id="studentId" wire:model.live="studentId" class="input">
                                    <option value="">{{ $canRequestOwn ? 'Izin saya sendiri' : 'Pilih mahasiswa' }}</option>
                                    @foreach ($recordable as $student)<option value="{{ $student->id }}">{{ $student->name }}</option>@endforeach
                                </select>
                            </x-field>
                        </div>
                    @endif
                    <x-field label="Jenis" name="type" required>
                        <select id="type" wire:model="type" class="input">
                            @foreach ($types as $type)
                                @continue($studentId !== '' && ! in_array($type->value, ['izin', 'sakit']))
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </x-field>
                    <div class="hidden sm:block"></div>
                    <x-field label="Mulai" name="starts_on" required><input id="starts_on" type="date" wire:model="starts_on" class="input"></x-field>
                    <x-field label="Selesai" name="ends_on" required><input id="ends_on" type="date" wire:model="ends_on" class="input"></x-field>
                    <div class="sm:col-span-2">
                        <x-field label="Alasan" name="reason"><textarea id="reason" wire:model="reason" rows="2" class="input"></textarea></x-field>
                    </div>
                    <div class="sm:col-span-2">
                        <x-field label="Lampiran" name="attachment" hint="Foto surat dokter atau surat izin (JPG, PNG, PDF, maks. 3 MB).">
                            <input id="attachment" type="file" wire:model="attachment" accept=".jpg,.jpeg,.png,.pdf" class="input">
                        </x-field>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <x-button type="submit" wire:loading.attr="disabled" wire:target="save,attachment">Simpan perubahan</x-button>
                    <x-button variant="secondary" wire:click="$set('showForm', false)">Batal</x-button>
                </div>
            </x-card>
        </form>
    @endif

    <x-card :padding="false">
        @forelse ($leaves as $leave)
            <div class="flex min-h-14 flex-wrap items-center gap-3 border-t border-border px-4 py-2.5 first:border-t-0" wire:key="leave-{{ $leave->id }}">
                <x-icon name="envelope" class="text-primary" />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">
                        {{ $leave->subject_type === 'student' ? ($studentNames[$leave->subject_id] ?? 'Mahasiswa') : $leave->requester->name }}
                        · {{ $leave->type->label() }}
                    </p>
                    <p class="truncate text-xs text-text-muted">
                        {{ $leave->starts_on->translatedFormat('j M') }}@if (! $leave->starts_on->isSameDay($leave->ends_on))–{{ $leave->ends_on->translatedFormat('j M Y') }}@else {{ $leave->starts_on->format('Y') }}@endif
                        @if ($leave->reason) · {{ $leave->reason }}@endif
                    </p>
                </div>
                <x-badge :variant="$leave->status->variant()">{{ $leave->status->label() }}</x-badge>
                @if ($canApprove && $leave->subject_type === 'teacher' && $leave->status === \App\Enums\LeaveStatus::Pending)
                    <div class="flex gap-2">
                        <x-button size="sm" wire:click="decide({{ $leave->id }}, 'approve')">Setujui</x-button>
                        <x-button size="sm" variant="secondary" wire:click="decide({{ $leave->id }}, 'reject')">Tolak</x-button>
                    </div>
                @endif
            </div>
        @empty
            <x-empty-state icon="envelope" title="Belum ada catatan izin" description="Izin mahasiswa yang kamu catat dan pengajuan izin dosen akan tampil di sini." />
        @endforelse
    </x-card>
</div>
