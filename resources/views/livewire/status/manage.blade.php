<div class="space-y-6">
    <x-page-header title="Status mahasiswa" subtitle="Setujui pengajuan cuti dan aktif kembali, serta catat status per semester." />

    @error('decision')<p class="rounded-lg border border-danger/40 bg-danger/10 p-3 text-sm text-danger" role="alert">{{ $message }}</p>@enderror

    <x-card title="Pengajuan menunggu" :padding="false">
        @forelse ($pending as $request)
            <div class="border-t border-border px-4 py-3 first:border-t-0" wire:key="pending-{{ $request->id }}">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium">{{ $request->student->name }} <span class="text-text-muted">· {{ $request->student->nim }}</span></p>
                        <p class="text-xs text-text-muted">{{ $request->type->label() }} · {{ $request->academicTerm->name }} {{ $request->academicTerm->semester }} · {{ $request->reason }}</p>
                    </div>
                    <div class="flex gap-2">
                        <x-button size="sm" wire:click="approve({{ $request->id }})" wire:confirm="Setujui pengajuan {{ $request->student->name }}?">Setujui</x-button>
                        <x-button size="sm" variant="secondary" wire:click="startReject({{ $request->id }})">Tolak</x-button>
                    </div>
                </div>
                @if ($rejectingId === $request->id)
                    <form wire:submit="reject" class="mt-2 space-y-2">
                        <x-field label="Alasan penolakan" name="rejectNote" required>
                            <input id="rejectNote" type="text" wire:model="rejectNote" class="input" @error('rejectNote') aria-invalid="true" @enderror>
                        </x-field>
                        <div class="flex gap-2">
                            <x-button type="submit" size="sm">Kirim penolakan</x-button>
                            <x-button type="button" size="sm" variant="secondary" wire:click="cancelReject">Batal</x-button>
                        </div>
                    </form>
                @endif
            </div>
        @empty
            <p class="px-4 py-4 text-sm text-text-muted">Tidak ada pengajuan yang menunggu.</p>
        @endforelse
    </x-card>

    <x-card title="Catat atau ubah status">
        <x-field label="Cari mahasiswa (nama atau NIM)" name="search">
            <input id="search" type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="Ketik minimal 2 huruf">
        </x-field>
        @if ($matches->isNotEmpty())
            <ul class="mt-2 divide-y divide-border rounded-lg border border-border">
                @foreach ($matches as $match)
                    <li wire:key="m-{{ $match->id }}">
                        <button type="button" wire:click="pick({{ $match->id }})" class="flex min-h-11 w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-surface-muted">
                            <span>{{ $match->name }} <span class="text-text-muted">· {{ $match->nim }}</span></span>
                            <x-badge :variant="$match->status->variant()">{{ $match->status->label() }}</x-badge>
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($student)
            <form wire:submit="saveStatus" class="mt-4 space-y-4">
                <p class="text-sm font-semibold">{{ $student->name }} · {{ $student->nim }} <x-badge :variant="$student->status->variant()">{{ $student->status->label() }}</x-badge></p>
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-field label="Semester" name="termId" required>
                        <select id="termId" wire:model="termId" class="input">
                            @foreach ($terms as $option)<option value="{{ $option->id }}">{{ $option->name }} · {{ $option->semester }}{{ $option->is_active ? ' (aktif)' : '' }}</option>@endforeach
                        </select>
                    </x-field>
                    <x-field label="Status" name="status" required>
                        <select id="status" wire:model="status" class="input">
                            @foreach ($statuses as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach
                        </select>
                    </x-field>
                    <x-field label="Alasan" name="reason" required>
                        <input id="reason" type="text" wire:model="reason" class="input" placeholder="Mis. SK Rektor No. ..." @error('reason') aria-invalid="true" @enderror>
                    </x-field>
                </div>
                <x-button type="submit" wire:loading.attr="disabled" wire:target="saveStatus">Simpan status</x-button>
            </form>

            <div class="mt-4">
                <p class="mb-1 text-sm font-semibold">Riwayat status</p>
                @forelse ($history as $row)
                    <div class="flex flex-wrap items-center gap-3 border-t border-border py-2 first:border-t-0" wire:key="h-{{ $row->id }}">
                        <span class="flex-1 text-sm">{{ $row->academicTerm->name }} · {{ $row->academicTerm->semester }}@if ($row->reason) <span class="text-text-muted">— {{ $row->reason }}</span>@endif</span>
                        <x-badge :variant="$row->status->variant()">{{ $row->status->label() }}</x-badge>
                    </div>
                @empty
                    <p class="text-sm text-text-muted">Belum ada catatan status.</p>
                @endforelse
            </div>
        @endif
    </x-card>

    <x-card title="Keputusan terakhir" :padding="false">
        @forelse ($recent as $request)
            <div class="flex flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0" wire:key="r-{{ $request->id }}">
                <span class="min-w-0 flex-1 truncate text-sm">{{ $request->student->name }} · {{ $request->type->label() }} · {{ $request->academicTerm->name }} {{ $request->academicTerm->semester }}</span>
                <x-badge :variant="$request->status->variant()">{{ $request->status->label() }}</x-badge>
            </div>
        @empty
            <p class="px-4 py-4 text-sm text-text-muted">Belum ada keputusan.</p>
        @endforelse
    </x-card>
</div>
