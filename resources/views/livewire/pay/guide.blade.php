@php $rp = fn ($value) => \App\Services\FinanceService::rupiah($value); @endphp
<div class="space-y-6">
    <x-page-header title="Panduan pembayaran" subtitle="Cara membayar UKT dan BPP lewat virtual account atau transfer dari bank di Indonesia.">
        <x-slot:actions>
            <x-button :href="route('pay.index')" wire:navigate variant="secondary" icon="arrow-left">Biaya Pendidikan</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-1 rounded-lg bg-surface-muted p-1" role="tablist" aria-label="Cara membayar">
        @foreach (['va' => 'Virtual account', 'transfer' => 'Transfer bank'] as $key => $label)
            <button type="button" role="tab" aria-selected="{{ $method === $key ? 'true' : 'false' }}" wire:click="$set('method', '{{ $key }}')"
                    class="flex min-h-11 items-center justify-center rounded-md text-sm font-semibold transition-colors {{ $method === $key ? 'bg-surface text-primary shadow-sm' : 'text-text-muted hover:text-text' }}">{{ $label }}</button>
        @endforeach
    </div>

    <x-card title="Pilih bank">
        <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Bank">
            @foreach ($banks as $key => $bankInfo)
                <button type="button" role="radio" aria-checked="{{ $bank === $key ? 'true' : 'false' }}" wire:click="$set('bank', '{{ $key }}')" wire:key="bank-{{ $key }}"
                        class="min-h-11 rounded-full border px-4 text-sm font-medium transition-colors {{ $bank === $key ? 'border-primary bg-primary text-primary-fg' : 'border-border bg-surface hover:bg-surface-muted' }}">{{ $bankInfo['name'] }}</button>
            @endforeach
        </div>
    </x-card>

    @if ($method === 'va')
        <x-card>
            <p class="text-sm text-text-muted">Nomor virtual account{{ $isExample ? ' (contoh, nomormu muncul di tagihan)' : ' kamu' }}</p>
            <p class="mt-1 text-2xl font-bold tabular-nums">{{ $vaNumber }}</p>
            @if ($invoice)<p class="mt-1 text-sm text-text-muted">Tagihan {{ $invoice->number }} · sisa {{ $rp($invoice->outstanding()) }}</p>@endif
            <p class="mt-2 text-xs text-text-muted">Nomor VA tetap sama tiap semester. Nominal yang dibayar harus sesuai tagihan; sistem menolak pembayaran melebihi sisa tagihan.</p>
        </x-card>
    @else
        <x-card>
            @if ($account['number'])
                <p class="text-sm text-text-muted">Rekening tujuan kampus</p>
                <p class="mt-1 text-lg font-bold">{{ $account['bank'] }} · <span class="tabular-nums">{{ $account['number'] }}</span></p>
                <p class="text-sm text-text-muted">a.n. {{ $account['holder'] }}</p>
            @else
                <p class="text-sm text-text-muted">Rekening tujuan belum diatur oleh BAAK. Hubungi helpdesk sebelum mentransfer.</p>
            @endif
            <p class="mt-2 text-xs text-text-muted">Transfer dari {{ $info['name'] }} (kode bank {{ $info['code'] }}). Biaya transfer antarbank mengikuti ketentuan bank pengirim.</p>
        </x-card>
    @endif

    @foreach ($steps as $channel => $list)
        <x-card :title="$channel" wire:key="ch-{{ $method }}-{{ $bank }}-{{ $loop->index }}">
            <ol class="list-decimal space-y-2 pl-5 text-sm">
                @foreach ($list as $step)<li>{{ $step }}</li>@endforeach
            </ol>
        </x-card>
    @endforeach

    <x-card title="Setelah membayar">
        <ul class="list-disc space-y-2 pl-5 text-sm">
            @foreach ($after as $item)<li>{{ $item }}</li>@endforeach
        </ul>
        <p class="mt-3 text-xs text-text-muted">Nama menu dan urutan layar dapat berbeda mengikuti versi aplikasi tiap bank. Jika ragu, pilih menu pembayaran atau transfer ke virtual account di aplikasi banknya, atau tanyakan helpdesk kampus.</p>
    </x-card>
</div>
