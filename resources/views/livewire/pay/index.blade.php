@php $rp = fn ($value) => \App\Services\FinanceService::rupiah($value); @endphp
<div class="mx-auto max-w-4xl space-y-6">
    <x-page-header title="Aksaraloka Pay" subtitle="Tagihan UKT dan BPP, status pembayaran, dan riwayat pembayaranmu.">
        <x-slot:actions>
            @if ($children->count() > 1)
                <select wire:model.live="childId" class="input" aria-label="Pilih anak">
                    @foreach ($children as $child)<option value="{{ $child->id }}" @selected($child->id === $student?->id)>{{ $child->name }}</option>@endforeach
                </select>
            @endif
            <x-button :href="route('pay.guide')" wire:navigate variant="secondary" icon="question-mark-circle">Panduan pembayaran</x-button>
        </x-slot:actions>
    </x-page-header>

    @if (! $student)
        <x-card><x-empty-state icon="credit-card" title="Data mahasiswa tidak ditemukan" description="Akunmu belum terhubung ke data mahasiswa." /></x-card>
    @else
        @if ($clearance && $requiresPayment)
            @if ($clearance['allowed'])
                <div class="rounded-lg border border-success/40 bg-success/10 p-3 text-sm" role="status">Pembayaran semester {{ $term->name }} {{ $term->semester }} sudah memenuhi syarat pengisian KRS.</div>
            @else
                <div class="rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm" role="status">
                    KRS semester {{ $term->name }} {{ $term->semester }} baru bisa diisi setelah tagihan lunas. Sisa tagihan: <strong>{{ $rp($clearance['outstanding']) }}</strong>.
                </div>
            @endif
        @endif

        <div class="grid gap-4 sm:grid-cols-3">
            <x-stat-tile label="Sisa tagihan" :value="$rp($outstanding)" />
            <x-stat-tile label="Jumlah tagihan" :value="$invoices->where('status', '!=', \App\Enums\InvoiceStatus::Cancelled)->count()" />
            <x-stat-tile label="Lunas" :value="$invoices->where('status', \App\Enums\InvoiceStatus::Paid)->count()" />
        </div>

        <x-card title="Tagihan" :padding="false">
            @forelse ($invoices as $invoice)
                <a href="{{ route('pay.show', $invoice) }}" wire:navigate wire:key="inv-{{ $invoice->id }}" class="flex min-h-14 flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0 hover:bg-surface-muted">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ $invoice->title }}</p>
                        <p class="truncate text-xs text-text-muted">{{ $invoice->number }} · jatuh tempo {{ $invoice->due_on->translatedFormat('j M Y') }}</p>
                    </div>
                    <span class="text-sm font-semibold tabular-nums">{{ $rp($invoice->total) }}</span>
                    @if ($invoice->isOverdue())<x-badge variant="danger">Lewat jatuh tempo</x-badge>@endif
                    <x-badge :variant="$invoice->status->variant()">{{ $invoice->status->label() }}</x-badge>
                </a>
            @empty
                <x-empty-state icon="credit-card" title="Belum ada tagihan" description="Tagihan muncul setelah BAAK menerbitkannya untuk semester ini." />
            @endforelse
        </x-card>
    @endif
</div>
