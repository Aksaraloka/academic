@php $rp = fn ($value) => \App\Services\FinanceService::rupiah($value); @endphp
<div class="space-y-6">
    <x-page-header title="Keuangan" subtitle="Tagihan UKT dan BPP, status pembayaran, dan verifikasi pembayaran mahasiswa.">
        @if ($canManage)
            <x-slot:actions>
                <x-button :href="route('finance.rates')" wire:navigate variant="secondary" icon="cog-6-tooth">Tarif</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    @if (! $term)
        <x-card><x-empty-state icon="calendar-days" title="Belum ada semester" description="Admin perlu menambahkan semester akademik lebih dulu." /></x-card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-field label="Semester" name="termId">
                <select id="termId" wire:model.live="termId" class="input">
                    @foreach ($terms as $option)<option value="{{ $option->id }}" @selected($option->id === $term->id)>{{ $option->name }} · {{ $option->semester }}{{ $option->is_active ? ' (aktif)' : '' }}</option>@endforeach
                </select>
            </x-field>
            <x-field label="Status" name="status">
                <select id="status" wire:model.live="status" class="input">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach
                </select>
            </x-field>
            <x-field label="Cari mahasiswa" name="search">
                <input id="search" type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="Nama atau NIM">
            </x-field>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat-tile label="Total ditagihkan" :value="$rp($stats['billed'])" :note="$stats['count'].' tagihan'" />
            <x-stat-tile label="Diterima" :value="$rp($stats['received'])" :note="$stats['paid'].' lunas'" />
            <x-stat-tile label="Belum dibayar" :value="$rp($stats['outstanding'])" :note="$stats['overdue'].' lewat jatuh tempo'" />
            <x-stat-tile label="Menunggu verifikasi" :value="$pending->count()" note="pembayaran" />
        </div>

        @error('payment')<p class="rounded-lg border border-danger/40 bg-danger/10 p-3 text-sm text-danger" role="alert">{{ $message }}</p>@enderror

        @if ($pending->isNotEmpty())
            <x-card title="Pembayaran menunggu verifikasi" :padding="false">
                @foreach ($pending as $payment)
                    <div class="border-t border-border px-4 py-3 first:border-t-0" wire:key="pend-{{ $payment->id }}">
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium">{{ $payment->student->name }} <span class="text-text-muted">· {{ $payment->student->nim }}</span></p>
                                <p class="text-xs text-text-muted">{{ $payment->invoice->number }} · {{ $payment->method->label() }} · {{ $payment->paid_on->translatedFormat('j M Y') }}@if ($payment->reference) · ref {{ $payment->reference }}@endif</p>
                            </div>
                            <span class="text-sm font-semibold tabular-nums">{{ $rp($payment->amount) }}</span>
                            @if ($payment->proof_path)<a href="{{ route('finance.proof', $payment) }}" target="_blank" rel="noopener" class="text-sm font-medium text-primary hover:underline">Bukti</a>@endif
                            @if ($canManage)
                                <div class="flex gap-2">
                                    <x-button size="sm" wire:click="approve({{ $payment->id }})" wire:confirm="Konfirmasi pembayaran {{ $rp($payment->amount) }} dari {{ $payment->student->name }}?">Konfirmasi</x-button>
                                    <x-button size="sm" variant="secondary" wire:click="startReject({{ $payment->id }})">Tolak</x-button>
                                </div>
                            @endif
                        </div>
                        @if ($rejectingId === $payment->id)
                            <form wire:submit="reject" class="mt-2 space-y-2">
                                <x-field label="Alasan penolakan" name="rejectNote" required><input id="rejectNote" type="text" wire:model="rejectNote" class="input" @error('rejectNote') aria-invalid="true" @enderror></x-field>
                                <div class="flex gap-2"><x-button type="submit" size="sm">Kirim penolakan</x-button><x-button type="button" size="sm" variant="secondary" wire:click="cancelReject">Batal</x-button></div>
                            </form>
                        @endif
                    </div>
                @endforeach
            </x-card>
        @endif

        @if ($canManage)
            <form wire:submit="generate">
                <x-card title="Terbitkan tagihan semester">
                    <p class="mb-3 text-sm text-text-muted">Membuat tagihan UKT dan BPP untuk mahasiswa aktif yang belum punya tagihan semester ini, sesuai tarif dan golongan UKT masing-masing.</p>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-field label="Jatuh tempo" name="dueOn" required><input id="dueOn" type="date" wire:model="dueOn" class="input"></x-field>
                        <x-field label="Program studi" name="programId">
                            <select id="programId" wire:model="programId" class="input">
                                <option value="">Semua program studi</option>
                                @foreach ($programs as $program)<option value="{{ $program->id }}">{{ $program->label() }}</option>@endforeach
                            </select>
                        </x-field>
                    </div>
                    <div class="mt-4"><x-button type="submit" icon="plus" wire:loading.attr="disabled" wire:target="generate" wire:confirm="Terbitkan tagihan untuk semester {{ $term->name }} {{ $term->semester }}?">Terbitkan tagihan</x-button></div>
                </x-card>
            </form>
        @endif

        <x-card title="Tagihan" :padding="false">
            @forelse ($invoices as $invoice)
                <a href="{{ route('finance.show', $invoice) }}" wire:navigate wire:key="inv-{{ $invoice->id }}" class="flex min-h-14 flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0 hover:bg-surface-muted">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ $invoice->student->name }} <span class="text-text-muted">· {{ $invoice->student->nim }}</span></p>
                        <p class="truncate text-xs text-text-muted">{{ $invoice->number }} · jatuh tempo {{ $invoice->due_on->translatedFormat('j M Y') }}</p>
                    </div>
                    <span class="text-sm tabular-nums">{{ $rp($invoice->total) }}</span>
                    @if ($invoice->isOverdue())<x-badge variant="danger">Lewat jatuh tempo</x-badge>@endif
                    @if ($invoice->krs_exempt)<x-badge variant="info">Dispensasi KRS</x-badge>@endif
                    <x-badge :variant="$invoice->status->variant()">{{ $invoice->status->label() }}</x-badge>
                </a>
            @empty
                <x-empty-state icon="banknotes" title="Belum ada tagihan" description="Terbitkan tagihan semester ini, atau ubah filter." />
            @endforelse
        </x-card>
    @endif
</div>
