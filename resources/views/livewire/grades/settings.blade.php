@php $letters = ['A' => 'A (angka mutu 4)', 'B' => 'B (angka mutu 3)', 'C' => 'C (angka mutu 2)', 'D' => 'D (angka mutu 1)']; @endphp
<div class="mx-auto max-w-4xl space-y-6">
    <x-page-header title="Pengaturan nilai" subtitle="Bobot bawaan, skala huruf mutu, dan remedial.">
        <x-slot:actions>
            <x-button :href="route('grades.completeness')" wire:navigate variant="secondary" icon="arrow-left">Kelengkapan nilai</x-button>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="save" class="space-y-4">
        <x-card title="Bobot bawaan">
            <p class="mb-3 text-sm text-text-muted">Dipakai kelas yang belum mengatur bobotnya sendiri. Dosen bisa mengubahnya per kelas sebelum nilai dikunci.</p>
            <div class="grid gap-3 sm:grid-cols-4">
                @foreach ($components as $key => $label)
                    <x-field :label="$label.' (%)'" name="weights.{{ $key }}" required>
                        <input id="weights.{{ $key }}" type="number" min="0" max="100" wire:model.live="weights.{{ $key }}" class="input text-right tabular-nums" inputmode="numeric" @error('weights.'.$key) aria-invalid="true" @enderror>
                    </x-field>
                @endforeach
            </div>
            <p class="mt-3 text-sm font-semibold tabular-nums {{ $weightSum === 100 ? 'text-success' : 'text-danger' }}" role="status">Jumlah bobot: {{ $weightSum }}%</p>
            @error('weights')<p class="text-sm text-danger" role="alert">{{ $message }}</p>@enderror
        </x-card>

        <x-card title="Skala huruf mutu">
            <p class="mb-3 text-sm text-text-muted">Batas nilai minimal untuk tiap huruf. Nilai di bawah batas D menjadi E (angka mutu 0).</p>
            <div class="grid gap-3 sm:grid-cols-4">
                @foreach ($letters as $letter => $label)
                    <x-field :label="'Minimal '.$label" name="thresholds.{{ $letter }}" required>
                        <input id="thresholds.{{ $letter }}" type="number" min="1" max="100" wire:model="thresholds.{{ $letter }}" class="input text-right tabular-nums" inputmode="numeric" @error('thresholds.'.$letter) aria-invalid="true" @enderror>
                    </x-field>
                @endforeach
            </div>
            @error('thresholds')<p class="mt-2 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
        </x-card>

        <x-card title="Remedial">
            <fieldset>
                <legend class="mb-1 text-sm text-text-muted">Huruf yang berhak mengikuti remedial. Hasil terbaik (asli atau remedial) dipakai di KHS dan transkrip.</legend>
                <div class="flex flex-wrap gap-x-6">
                    @foreach (['C', 'D', 'E'] as $letter)
                        <label class="flex min-h-11 items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="remedialLetters" value="{{ $letter }}" class="size-5 accent-primary"> Nilai {{ $letter }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        </x-card>

        <x-card title="Batas waktu input nilai">
            <p class="text-sm text-text-muted">Batas input nilai diatur per semester di <a href="{{ route('academic.terms') }}" wire:navigate class="font-medium text-primary hover:underline">Semester akademik</a> dan tampil di Kalender akademik. BAAK bisa memperpanjangnya per kelas dari menu Kelengkapan nilai.</p>
        </x-card>

        <x-button type="submit" wire:loading.attr="disabled" wire:target="save">Simpan pengaturan</x-button>
    </form>
</div>
