<div class="space-y-6">
    <x-page-header title="Status kelulusan" subtitle="Syarat lulus dicek otomatis dari transkripmu.">
        <x-slot:actions><x-button :href="route('graduation.overview')" wire:navigate variant="secondary" icon="academic-cap">Wisuda</x-button></x-slot:actions>
    </x-page-header>

    @if ($decision)
        <x-card>
            <div class="flex flex-wrap items-center gap-3">
                <x-badge variant="success" icon="check-circle">Dinyatakan lulus</x-badge>
                <x-badge :variant="$decision->predicate === 'Dengan Pujian' ? 'primary' : 'info'">{{ $decision->predicate }}</x-badge>
            </div>
            <p class="mt-2 text-sm text-text-muted">{{ $decision->yudisium->name }} · {{ $decision->yudisium->held_on->translatedFormat('j F Y') }} · {{ $decision->credits }} SKS · IPK {{ number_format((float) $decision->gpa, 2, ',', '.') }}</p>
            <div class="mt-3"><x-button :href="route('yudisium.certificate', $decision)" icon="arrow-down-tray" download>Unduh Surat Keterangan Lulus</x-button></div>
        </x-card>
    @else
        <x-card>
            <div class="flex flex-wrap items-center gap-3">
                <x-badge :variant="$result['eligible'] ? 'success' : 'warning'">{{ $result['eligible'] ? 'Memenuhi syarat lulus' : 'Belum memenuhi syarat lulus' }}</x-badge>
                @if ($result['predicate'])<x-badge variant="primary">Perkiraan predikat: {{ $result['predicate'] }}</x-badge>@endif
            </div>
            <p class="mt-2 text-sm text-text-muted">Penetapan kelulusan dilakukan program studi pada yudisium. {{ $thesis ? '' : 'Ajukan judul tugas akhir di menu Tugas akhir.' }}</p>
        </x-card>
    @endif

    <x-card title="Syarat lulus" :padding="false">
        @foreach ($result['checks'] as $check)
            <div class="flex min-h-12 flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0" wire:key="chk-{{ $check['key'] }}">
                <span class="min-w-0 flex-1 text-sm">{{ $check['label'] }}</span>
                <span class="text-sm tabular-nums text-text-muted">{{ $check['detail'] }}</span>
                <x-badge :variant="$check['met'] ? 'success' : 'danger'">{{ $check['met'] ? 'Terpenuhi' : 'Belum' }}</x-badge>
            </div>
        @endforeach
    </x-card>
</div>
