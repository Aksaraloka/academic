<div class="space-y-6">
    <x-page-header :title="$yudisium->name" :subtitle="$yudisium->held_on->translatedFormat('l, j F Y').($yudisium->number ? ' · '.$yudisium->number : '')">
        <x-slot:actions>
            <x-button :href="route('yudisium.index')" wire:navigate variant="secondary" icon="arrow-left">Yudisium</x-button>
            @if ($yudisium->isFinalized() && $decisions->isNotEmpty())<x-button :href="route('yudisium.pdf', $yudisium)" icon="arrow-down-tray" download>Rekap PDF</x-button>@endif
        </x-slot:actions>
    </x-page-header>

    @error('selected')<p class="rounded-lg border border-danger/40 bg-danger/10 p-3 text-sm text-danger" role="alert">{{ $message }}</p>@enderror

    @if ($yudisium->isFinalized())
        <x-card title="Dinyatakan lulus" :padding="false">
            @forelse ($decisions as $decision)
                <div class="flex min-h-14 flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0" wire:key="d-{{ $decision->id }}">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">{{ $decision->student->name }} <span class="text-text-muted">· {{ $decision->student->nim }}</span></p>
                        <p class="truncate text-xs text-text-muted">{{ $decision->student->program?->label() }} · {{ $decision->credits }} SKS · IPK {{ number_format((float) $decision->gpa, 2, ',', '.') }}</p>
                    </div>
                    <x-badge :variant="$decision->predicate === 'Dengan Pujian' ? 'primary' : 'success'">{{ $decision->predicate }}</x-badge>
                    <x-button size="sm" variant="secondary" :href="route('yudisium.certificate', $decision)" download>SKL PDF</x-button>
                </div>
            @empty
                <p class="px-4 py-4 text-sm text-text-muted">Tidak ada keputusan kelulusan.</p>
            @endforelse
        </x-card>
    @else
        <x-card>
            <p class="text-sm text-text-muted">Calon yudisium: mahasiswa aktif yang tugas akhirnya sudah lulus sidang (atau, bila kampus tidak mewajibkan tugas akhir, angkatan yang sudah menempuh lebih dari tiga tahun). Syarat dicek otomatis dari transkrip saat halaman dibuka.</p>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" wire:model.live="onlyEligible" class="size-5 accent-primary"> Hanya yang memenuhi syarat</label>
                <x-button size="sm" variant="secondary" wire:click="selectEligible">Pilih semua yang memenuhi syarat</x-button>
            </div>
        </x-card>

        <x-card :padding="false">
            @forelse ($rows as $row)
                @php $student = $row['student']; $result = $row['result']; @endphp
                <div class="border-t border-border px-4 py-3 first:border-t-0" wire:key="c-{{ $student->id }}">
                    <div class="flex flex-wrap items-center gap-3">
                        <input type="checkbox" wire:model="selected" value="{{ $student->id }}" class="size-5 shrink-0 accent-primary" @disabled(! $result['eligible']) aria-label="Pilih {{ $student->name }}">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ $student->name }} <span class="text-text-muted">· {{ $student->nim }}</span></p>
                            <p class="truncate text-xs text-text-muted">{{ $student->program?->label() }} · {{ $result['credits'] }} SKS · IPK {{ $result['gpa'] !== null ? number_format($result['gpa'], 2, ',', '.') : '-' }}</p>
                        </div>
                        @if ($result['eligible'])<x-badge variant="success">Memenuhi syarat · {{ $result['predicate'] }}</x-badge>@else<x-badge variant="danger">Belum memenuhi syarat</x-badge>@endif
                    </div>
                    <details class="mt-1 pl-8">
                        <summary class="min-h-11 cursor-pointer py-2 text-sm font-medium text-primary">Rincian syarat</summary>
                        <ul class="space-y-1 pb-2 text-sm">
                            @foreach ($result['checks'] as $check)
                                <li class="flex flex-wrap items-center gap-2"><x-badge :variant="$check['met'] ? 'success' : 'danger'">{{ $check['met'] ? 'OK' : 'Belum' }}</x-badge> {{ $check['label'] }} <span class="text-text-muted">— {{ $check['detail'] }}</span></li>
                            @endforeach
                        </ul>
                    </details>
                </div>
            @empty
                <x-empty-state icon="academic-cap" title="Belum ada calon yudisium" description="Calon muncul setelah tugas akhir mahasiswa dinyatakan lulus sidang." />
            @endforelse
        </x-card>

        @if ($rows->isNotEmpty())
            <x-card title="Tetapkan kelulusan">
                <p class="mb-3 text-sm text-text-muted">{{ count($selected) }} mahasiswa dipilih. Mahasiswa yang dinyatakan lulus berstatus Lulus dan bisa mendaftar wisuda.</p>
                <div class="max-w-sm"><x-field label="Nomor SK yudisium" name="number"><input id="number" type="text" wire:model="number" class="input" placeholder="Opsional"></x-field></div>
                <div class="mt-4"><x-button wire:click="finalize" wire:confirm="Tetapkan kelulusan untuk mahasiswa terpilih? Status mereka berubah menjadi Lulus." :disabled="$selected === []">Tetapkan kelulusan</x-button></div>
            </x-card>
        @endif
    @endif
</div>
