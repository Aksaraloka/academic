@php $rp = fn ($value) => \App\Services\FinanceService::rupiah($value); $open = $invoice->status !== \App\Enums\InvoiceStatus::Paid && $invoice->status !== \App\Enums\InvoiceStatus::Cancelled; @endphp
<div class="mx-auto max-w-4xl space-y-6">
    <x-page-header :title="$invoice->number" :subtitle="$invoice->student->name.' · '.$invoice->student->nim">
        <x-slot:actions>
            <x-button :href="route('finance.index', ['term' => $invoice->academic_term_id])" wire:navigate variant="secondary" icon="arrow-left">Keuangan</x-button>
            <x-button :href="route('finance.pdf', $invoice)" variant="secondary" icon="arrow-down-tray">PDF</x-button>
        </x-slot:actions>
    </x-page-header>

    @error('payment')<p class="rounded-lg border border-danger/40 bg-danger/10 p-3 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
    @error('invoice')<p class="rounded-lg border border-danger/40 bg-danger/10 p-3 text-sm text-danger" role="alert">{{ $message }}</p>@enderror

    <x-card>
        <div class="flex flex-wrap items-center gap-3">
            <x-badge :variant="$invoice->status->variant()">{{ $invoice->status->label() }}</x-badge>
            @if ($invoice->isOverdue())<x-badge variant="danger">Lewat jatuh tempo</x-badge>@endif
            @if ($invoice->krs_exempt)<x-badge variant="info">Dispensasi KRS</x-badge>@endif
            <span class="text-sm text-text-muted">{{ $invoice->academicTerm->name }} · {{ $invoice->academicTerm->semester }} · jatuh tempo {{ $invoice->due_on->translatedFormat('j F Y') }}</span>
        </div>
        <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
            <div><dt class="text-text-muted">Virtual account</dt><dd class="font-medium tabular-nums">{{ $invoice->va_number ?? '-' }}</dd></div>
            <div><dt class="text-text-muted">Program studi</dt><dd class="font-medium">{{ $invoice->student->program?->label() ?? '-' }}</dd></div>
        </dl>
    </x-card>

    <x-card title="Rincian" :padding="false">
        @foreach ($invoice->items as $item)
            <div class="flex justify-between gap-3 border-t border-border px-4 py-2.5 text-sm first:border-t-0"><span>{{ $item->description }}</span><span class="tabular-nums">{{ $rp($item->amount) }}</span></div>
        @endforeach
        <div class="flex justify-between gap-3 border-t border-border px-4 py-2.5 text-sm font-semibold"><span>Total</span><span class="tabular-nums">{{ $rp($invoice->total) }}</span></div>
        <div class="flex justify-between gap-3 border-t border-border px-4 py-2.5 text-sm"><span>Sudah dibayar</span><span class="tabular-nums">{{ $rp($invoice->paidAmount()) }}</span></div>
        <div class="flex justify-between gap-3 border-t border-border px-4 py-2.5 text-sm font-semibold"><span>Sisa</span><span class="tabular-nums">{{ $rp($invoice->outstanding()) }}</span></div>
    </x-card>

    <x-card title="Riwayat pembayaran" :padding="false">
        @forelse ($invoice->payments->sortByDesc('id') as $payment)
            <div class="border-t border-border px-4 py-3 first:border-t-0" wire:key="pay-{{ $payment->id }}">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium">{{ $payment->method->label() }} · {{ $payment->paid_on->translatedFormat('j M Y') }}@if ($payment->reference) · ref {{ $payment->reference }}@endif</p>
                        @if ($payment->note)<p class="text-xs text-text-muted">{{ $payment->note }}</p>@endif
                        @if ($payment->verifier)<p class="text-xs text-text-muted">Diverifikasi {{ $payment->verifier->name }}</p>@endif
                    </div>
                    <span class="text-sm font-semibold tabular-nums">{{ $rp($payment->amount) }}</span>
                    @if ($payment->proof_path)<a href="{{ route('finance.proof', $payment) }}" target="_blank" rel="noopener" class="text-sm font-medium text-primary hover:underline">Bukti</a>@endif
                    <x-badge :variant="$payment->status->variant()">{{ $payment->status->label() }}</x-badge>
                    @if ($canManage && $payment->status === \App\Enums\PaymentStatus::Pending)
                        <div class="flex gap-2">
                            <x-button size="sm" wire:click="approve({{ $payment->id }})">Konfirmasi</x-button>
                            <x-button size="sm" variant="secondary" wire:click="startReject({{ $payment->id }})">Tolak</x-button>
                        </div>
                    @endif
                </div>
                @if ($rejectingId === $payment->id)
                    <form wire:submit="reject" class="mt-2 space-y-2">
                        <x-field label="Alasan penolakan" name="rejectNote" required><input id="rejectNote" type="text" wire:model="rejectNote" class="input" @error('rejectNote') aria-invalid="true" @enderror></x-field>
                        <x-button type="submit" size="sm">Kirim penolakan</x-button>
                    </form>
                @endif
            </div>
        @empty
            <p class="px-4 py-4 text-sm text-text-muted">Belum ada pembayaran.</p>
        @endforelse
    </x-card>

    @if ($canManage && $open)
        <form wire:submit="record">
            <x-card title="Catat pembayaran">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-field label="Jumlah (Rp)" name="amount" required><input id="amount" type="number" min="1" wire:model="amount" class="input text-right tabular-nums" inputmode="numeric" @error('amount') aria-invalid="true" @enderror></x-field>
                    <x-field label="Metode" name="method" required>
                        <select id="method" wire:model="method" class="input">@foreach ($methods as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach</select>
                    </x-field>
                    <x-field label="Tanggal bayar" name="paid_on" required><input id="paid_on" type="date" wire:model="paid_on" class="input" max="{{ today()->toDateString() }}"></x-field>
                    <x-field label="Referensi" name="reference"><input id="reference" type="text" wire:model="reference" class="input" placeholder="No. transaksi"></x-field>
                    <div class="sm:col-span-2 lg:col-span-4"><x-field label="Catatan" name="note"><input id="note" type="text" wire:model="note" class="input"></x-field></div>
                </div>
                <div class="mt-4"><x-button type="submit" wire:loading.attr="disabled" wire:target="record">Catat pembayaran</x-button></div>
            </x-card>
        </form>
    @endif

    @if ($canManage && $invoice->status !== \App\Enums\InvoiceStatus::Cancelled)
        <x-card title="Tindakan lain">
            <div class="flex flex-wrap items-center gap-3">
                <x-button variant="secondary" wire:click="toggleExempt">{{ $invoice->krs_exempt ? 'Cabut dispensasi KRS' : 'Beri dispensasi KRS' }}</x-button>
                <p class="text-xs text-text-muted">Dispensasi membolehkan mahasiswa mengisi KRS walau tagihan belum lunas.</p>
            </div>
            @if ($invoice->payments->where('status', \App\Enums\PaymentStatus::Confirmed)->isEmpty())
                <form wire:submit="cancelInvoice" class="mt-4 flex flex-wrap items-end gap-3">
                    <div class="min-w-64 flex-1"><x-field label="Batalkan tagihan" name="cancelReason"><input id="cancelReason" type="text" wire:model="cancelReason" class="input" placeholder="Alasan pembatalan" @error('cancelReason') aria-invalid="true" @enderror></x-field></div>
                    <x-button type="submit" variant="danger" wire:confirm="Batalkan tagihan ini?">Batalkan</x-button>
                </form>
            @endif
        </x-card>
    @endif
</div>
