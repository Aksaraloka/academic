@php $rp = fn ($value) => \App\Services\FinanceService::rupiah($value); @endphp
<div class="mx-auto max-w-4xl space-y-6">
    <x-page-header :title="$invoice->title" :subtitle="$invoice->number">
        <x-slot:actions>
            <x-button :href="route('pay.index')" wire:navigate variant="secondary" icon="arrow-left">Aksaraloka Pay</x-button>
            <x-button :href="route('finance.pdf', $invoice)" variant="secondary" icon="arrow-down-tray">PDF</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <div class="flex flex-wrap items-center gap-3">
            <x-badge :variant="$invoice->status->variant()">{{ $invoice->status->label() }}</x-badge>
            @if ($invoice->isOverdue())<x-badge variant="danger">Lewat jatuh tempo</x-badge>@endif
            @if ($invoice->krs_exempt)<x-badge variant="info">Dispensasi KRS</x-badge>@endif
            <span class="text-sm text-text-muted">Jatuh tempo {{ $invoice->due_on->translatedFormat('j F Y') }}</span>
        </div>
    </x-card>

    <x-card title="Rincian" :padding="false">
        @foreach ($invoice->items as $item)
            <div class="flex justify-between gap-3 border-t border-border px-4 py-2.5 text-sm first:border-t-0"><span>{{ $item->description }}</span><span class="tabular-nums">{{ $rp($item->amount) }}</span></div>
        @endforeach
        <div class="flex justify-between gap-3 border-t border-border px-4 py-2.5 text-sm font-semibold"><span>Total</span><span class="tabular-nums">{{ $rp($invoice->total) }}</span></div>
        <div class="flex justify-between gap-3 border-t border-border px-4 py-2.5 text-sm"><span>Sudah dibayar</span><span class="tabular-nums">{{ $rp($invoice->paidAmount()) }}</span></div>
        @if ($invoice->pendingAmount() > 0)
            <div class="flex justify-between gap-3 border-t border-border px-4 py-2.5 text-sm text-text-muted"><span>Menunggu verifikasi</span><span class="tabular-nums">{{ $rp($invoice->pendingAmount()) }}</span></div>
        @endif
        <div class="flex justify-between gap-3 border-t border-border px-4 py-2.5 text-sm font-semibold"><span>Sisa tagihan</span><span class="tabular-nums">{{ $rp($invoice->outstanding()) }}</span></div>
    </x-card>

    @if ($canPay)
        <x-card title="Cara membayar">
            <x-slot:actions>
                <x-button size="sm" variant="secondary" :href="route('pay.guide')" wire:navigate icon="question-mark-circle">Panduan per bank</x-button>
            </x-slot:actions>
            <ul class="space-y-2 text-sm">
                @if ($bank['number'])
                    <li><span class="text-text-muted">Transfer bank:</span> <strong>{{ $bank['name'] }} {{ $bank['number'] }}</strong> a.n. {{ $bank['holder'] }}</li>
                @endif
                @if ($invoice->va_number)
                    <li><span class="text-text-muted">Virtual account:</span> <strong class="tabular-nums">{{ $invoice->va_number }}</strong> (tercatat otomatis setelah pembayaran diterima)</li>
                @endif
            </ul>
        </x-card>

        <form wire:submit="submit">
            <x-card title="Konfirmasi pembayaran">
                <p class="mb-3 text-sm text-text-muted">Sudah transfer? Isi data di bawah. Petugas keuangan akan memverifikasi, lalu status tagihan diperbarui.</p>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-field label="Jumlah (Rp)" name="amount" required><input id="amount" type="number" min="1" wire:model="amount" class="input text-right tabular-nums" inputmode="numeric" @error('amount') aria-invalid="true" @enderror></x-field>
                    <x-field label="Metode" name="method" required>
                        <select id="method" wire:model="method" class="input">@foreach ($methods as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach</select>
                    </x-field>
                    <x-field label="Tanggal bayar" name="paid_on" required><input id="paid_on" type="date" wire:model="paid_on" class="input" max="{{ today()->toDateString() }}"></x-field>
                    <x-field label="No. referensi" name="reference"><input id="reference" type="text" wire:model="reference" class="input"></x-field>
                    <div class="sm:col-span-2 lg:col-span-4">
                        <x-field label="Bukti pembayaran" name="proof" hint="Foto atau PDF, maksimal 3 MB."><input id="proof" type="file" wire:model="proof" accept="image/*,.pdf" class="input"></x-field>
                    </div>
                </div>
                <div class="mt-4"><x-button type="submit" wire:loading.attr="disabled" wire:target="submit,proof">Kirim konfirmasi</x-button></div>
            </x-card>
        </form>
    @endif

    <x-card title="Riwayat pembayaran" :padding="false">
        @forelse ($invoice->payments->sortByDesc('id') as $payment)
            <div class="flex min-h-14 flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0" wire:key="pay-{{ $payment->id }}">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium">{{ $payment->method->label() }} · {{ $payment->paid_on->translatedFormat('j M Y') }}@if ($payment->reference) · ref {{ $payment->reference }}@endif</p>
                    @if ($payment->status === \App\Enums\PaymentStatus::Rejected && $payment->note)<p class="text-xs text-danger">Ditolak: {{ $payment->note }}</p>@endif
                </div>
                <span class="text-sm font-semibold tabular-nums">{{ $rp($payment->amount) }}</span>
                <x-badge :variant="$payment->status->variant()">{{ $payment->status->label() }}</x-badge>
            </div>
        @empty
            <p class="px-4 py-4 text-sm text-text-muted">Belum ada pembayaran.</p>
        @endforelse
    </x-card>
</div>
