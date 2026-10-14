@php
    $rp = fn (int $value): string => \App\Services\FinanceService::rupiah($value);
    $confirmed = $invoice->payments->where('status', \App\Enums\PaymentStatus::Confirmed);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        @page { margin: 18mm 14mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #0f172a; }
        .kop { border-bottom: 2px solid #c62828; padding-bottom: 6px; margin-bottom: 10px; }
        .kop h1 { margin: 0; font-size: 14pt; }
        .kop p { margin: 2px 0 0; color: #475569; }
        h2 { text-align: center; font-size: 12pt; margin: 8px 0 10px; text-transform: uppercase; }
        h3 { font-size: 9.5pt; margin: 12px 0 4px; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.meta td { padding: 2px 0; }
        table.meta td.label { width: 22%; color: #475569; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 4px; font-size: 8.5pt; text-align: left; }
        table.grid td { border: 1px solid #cbd5e1; padding: 4px; }
        .r { text-align: right; }
        .total td { font-weight: bold; background: #f8fafc; }
        .status { display: inline-block; padding: 2px 8px; border: 1px solid #94a3b8; border-radius: 10px; font-size: 8pt; }
        .foot { margin-top: 14px; font-size: 7.5pt; color: #64748b; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $tenant->name }}</h1>
        <p>Aksaraloka Pay · Keuangan Akademik</p>
    </div>

    <h2>{{ $invoice->status === \App\Enums\InvoiceStatus::Paid ? 'Bukti Pembayaran Lunas' : 'Tagihan Pembayaran' }}</h2>

    <table class="meta">
        <tr><td class="label">Nomor</td><td><b>{{ $invoice->number }}</b></td><td class="label">Status</td><td><span class="status">{{ $invoice->status->label() }}</span></td></tr>
        <tr><td class="label">Nama</td><td>{{ $invoice->student->name }}</td><td class="label">Semester</td><td>{{ $invoice->academicTerm->name }} · {{ $invoice->academicTerm->semester }}</td></tr>
        <tr><td class="label">NIM</td><td>{{ $invoice->student->nim }}</td><td class="label">Jatuh tempo</td><td>{{ $invoice->due_on->translatedFormat('j F Y') }}</td></tr>
        <tr><td class="label">Program studi</td><td>{{ $invoice->student->program?->label() ?? '-' }}</td><td class="label">Virtual account</td><td>{{ $invoice->va_number ?? '-' }}</td></tr>
    </table>

    <h3>Rincian tagihan</h3>
    <table class="grid">
        <thead><tr><th>Komponen</th><th class="r">Jumlah</th></tr></thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr><td>{{ $item->description }}</td><td class="r">{{ $rp($item->amount) }}</td></tr>
            @endforeach
            <tr class="total"><td>Total tagihan</td><td class="r">{{ $rp($invoice->total) }}</td></tr>
            <tr><td>Sudah dibayar</td><td class="r">{{ $rp($invoice->paidAmount()) }}</td></tr>
            <tr class="total"><td>Sisa tagihan</td><td class="r">{{ $rp($invoice->outstanding()) }}</td></tr>
        </tbody>
    </table>

    <h3>Riwayat pembayaran</h3>
    <table class="grid">
        <thead><tr><th>Tanggal</th><th>Metode</th><th>Referensi</th><th>Status</th><th class="r">Jumlah</th></tr></thead>
        <tbody>
            @forelse ($invoice->payments->sortBy('paid_on') as $payment)
                <tr><td>{{ $payment->paid_on->translatedFormat('j M Y') }}</td><td>{{ $payment->method->label() }}</td><td>{{ $payment->reference ?? '-' }}</td><td>{{ $payment->status->label() }}</td><td class="r">{{ $rp($payment->amount) }}</td></tr>
            @empty
                <tr><td colspan="5">Belum ada pembayaran.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($invoice->status !== \App\Enums\InvoiceStatus::Paid && $tenant->setting('payment_account_number'))
        <h3>Cara pembayaran</h3>
        <p>Transfer ke {{ $tenant->setting('payment_bank_name') }} {{ $tenant->setting('payment_account_number') }} a.n. {{ $tenant->setting('payment_account_name') }}, atau bayar lewat virtual account {{ $invoice->va_number }}. Setelah transfer, konfirmasi pembayaran di Aksaraloka Pay.</p>
    @endif

    <p class="foot">Dicetak oleh {{ $printedBy }} pada {{ now()->translatedFormat('j F Y H:i') }}.</p>
</body>
</html>
