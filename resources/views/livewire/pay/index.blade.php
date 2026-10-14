@php $rp = fn ($value) => \App\Services\FinanceService::rupiah($value); @endphp
<div class="space-y-6">
    <x-page-header title="Biaya Pendidikan" subtitle="Tagihan UKT dan BPP, status pembayaran, dan riwayat pembayaranmu.">
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
            <x-stat-tile label="Total tagihan" :value="$rp($total)" />
            <x-stat-tile label="Belum dibayar" :value="$rp($outstanding)" :note="$outstanding > 0 ? 'Perlu dilunasi' : 'Semua lunas'" :tone="$outstanding > 0 ? 'text-warning' : 'text-success'" />
            <x-stat-tile label="Batas akhir pembayaran" :value="$nextDue ? $nextDue->translatedFormat('j M Y') : '–'" :note="$nextDue ? null : 'Tidak ada tagihan berjalan'" />
        </div>

        <div class="flex gap-6 border-b border-border" role="tablist" aria-label="Biaya pendidikan">
            @foreach (['invoices' => 'Tagihan', 'payments' => 'Riwayat pembayaran'] as $key => $label)
                <button type="button" role="tab" wire:click="$set('tab', '{{ $key }}')" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                        class="-mb-px min-h-11 border-b-2 px-1 text-sm font-medium transition {{ $tab === $key ? 'border-primary text-primary' : 'border-transparent text-text-muted hover:text-text' }}">{{ $label }}</button>
            @endforeach
        </div>

        @if ($tab === 'payments')
            <x-card title="Riwayat pembayaran" :padding="false">
                @if ($payments->isEmpty())
                    <x-empty-state icon="credit-card" title="Belum ada pembayaran" description="Pembayaran yang kamu lakukan atau konfirmasi muncul di sini." />
                @else
                    <table class="table-responsive">
                        <thead><tr><th scope="col">Tanggal</th><th scope="col">Tagihan</th><th scope="col">Metode</th><th scope="col" class="text-right">Nominal</th><th scope="col">Status</th></tr></thead>
                        <tbody>
                            @foreach ($payments as $payment)
                                <tr wire:key="pay-{{ $payment->id }}">
                                    <td data-label="Tanggal">{{ $payment->paid_on->translatedFormat('j M Y') }}</td>
                                    <td data-label="Tagihan" class="font-medium">{{ $payment->invoice->title }}</td>
                                    <td data-label="Metode">{{ $payment->method->label() }}</td>
                                    <td data-label="Nominal" class="tabular-nums sm:text-right">{{ $rp($payment->amount) }}</td>
                                    <td data-label="Status"><x-badge :variant="$payment->status->variant()">{{ $payment->status->label() }}</x-badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>
        @else
            <x-card title="Daftar tagihan" :padding="false">
                @if ($invoices->isEmpty())
                    <x-empty-state icon="credit-card" title="Belum ada tagihan" description="Tagihan muncul setelah BAAK menerbitkannya untuk semester ini." />
                @else
                    <table class="table-responsive">
                        <thead><tr><th scope="col">No</th><th scope="col">Nama tagihan</th><th scope="col">Periode</th><th scope="col" class="text-right">Nominal</th><th scope="col">Batas akhir</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Rincian</span></th></tr></thead>
                        <tbody>
                            @foreach ($invoices as $invoice)
                                <tr wire:key="inv-{{ $invoice->id }}">
                                    <td data-label="No" class="tabular-nums text-text-muted">{{ $loop->iteration }}</td>
                                    <td data-label="Nama tagihan" class="font-medium">{{ $invoice->title }}<span class="block text-xs font-normal text-text-muted">{{ $invoice->number }}</span></td>
                                    <td data-label="Periode">{{ $invoice->academicTerm ? $invoice->academicTerm->name.' '.$invoice->academicTerm->semester : '–' }}</td>
                                    <td data-label="Nominal" class="tabular-nums sm:text-right">{{ $rp($invoice->total) }}</td>
                                    <td data-label="Batas akhir">{{ $invoice->due_on->translatedFormat('j F Y') }}</td>
                                    <td data-label="Status">
                                        <x-badge :variant="$invoice->status->variant()">{{ $invoice->status->label() }}</x-badge>
                                        @if ($invoice->isOverdue())<x-badge variant="danger">Lewat jatuh tempo</x-badge>@endif
                                    </td>
                                    <td><x-button size="sm" variant="secondary" :href="route('pay.show', $invoice)" wire:navigate>Rincian</x-button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>
        @endif
    @endif
</div>
