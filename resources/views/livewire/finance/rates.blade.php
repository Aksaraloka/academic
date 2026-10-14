@php $rp = fn ($value) => \App\Services\FinanceService::rupiah($value); @endphp
<div class="space-y-6">
    <x-page-header title="Tarif keuangan" subtitle="UKT per golongan dan BPP per program studi. Dipakai saat menerbitkan tagihan semester.">
        <x-slot:actions>
            <x-button :href="route('finance.index')" wire:navigate variant="secondary" icon="arrow-left">Keuangan</x-button>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="save">
        <x-card title="Atur tarif">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-field label="Program studi" name="programId" required>
                    <select id="programId" wire:model="programId" class="input">
                        <option value="">Pilih</option>
                        @foreach ($programs as $program)<option value="{{ $program->id }}">{{ $program->label() }}</option>@endforeach
                    </select>
                </x-field>
                <x-field label="Jenis" name="kind" required>
                    <select id="kind" wire:model.live="kind" class="input"><option value="ukt">UKT</option><option value="bpp">BPP</option></select>
                </x-field>
                @if ($kind === 'ukt')
                    <x-field label="Golongan UKT" name="ukt_group" hint="Kosongkan untuk tarif bawaan semua golongan.">
                        <select id="ukt_group" wire:model="ukt_group" class="input"><option value="">Semua golongan</option>@foreach (range(1, 8) as $group)<option value="{{ $group }}">Golongan {{ $group }}</option>@endforeach</select>
                    </x-field>
                @endif
                <x-field label="Tarif per semester (Rp)" name="amount" required>
                    <input id="amount" type="number" min="1" wire:model="amount" class="input text-right tabular-nums" inputmode="numeric" @error('amount') aria-invalid="true" @enderror>
                </x-field>
            </div>
            <div class="mt-4"><x-button type="submit" wire:loading.attr="disabled" wire:target="save">Simpan tarif</x-button></div>
        </x-card>
    </form>

    @foreach ($programs as $program)
        <x-card :title="$program->label()" :padding="false" wire:key="prog-{{ $program->id }}">
            @forelse ($program->feeRates->sortBy([['kind', 'desc'], ['ukt_group', 'asc']]) as $rate)
                <div class="flex min-h-12 items-center gap-3 border-t border-border px-4 py-2 first:border-t-0" wire:key="rate-{{ $rate->id }}">
                    <span class="flex-1 text-sm">{{ strtoupper($rate->kind) }}{{ $rate->kind === 'ukt' ? ($rate->ukt_group ? ' · Golongan '.$rate->ukt_group : ' · Bawaan') : '' }}</span>
                    <span class="text-sm font-semibold tabular-nums">{{ $rp($rate->amount) }}</span>
                    <x-button size="sm" variant="secondary" wire:click="remove({{ $rate->id }})" wire:confirm="Hapus tarif ini?">Hapus</x-button>
                </div>
            @empty
                <p class="px-4 py-3 text-sm text-text-muted">Belum ada tarif.</p>
            @endforelse
        </x-card>
    @endforeach
</div>
