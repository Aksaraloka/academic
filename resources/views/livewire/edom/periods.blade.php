<div class="space-y-6">
    <x-page-header title="Periode evaluasi dosen" subtitle="EDOM: mahasiswa menilai dosen tiap mata kuliah yang diikuti, secara anonim.">
        <x-slot:actions>
            <x-button :href="route('evaluations.results')" wire:navigate variant="secondary" icon="chart-bar">Hasil evaluasi</x-button>
            @if (auth()->user()->role === \App\Enums\Role::Admin)<x-button :href="route('survey.create')" wire:navigate variant="secondary" icon="plus">Buat kuesioner</x-button>@endif
        </x-slot:actions>
    </x-page-header>

    @if ($canManage)
        <form wire:submit="save">
            <x-card :title="$editingId ? 'Ubah periode' : 'Periode baru'">
                <p class="mb-3 text-sm text-text-muted">Kuesioner berkategori <strong>Evaluasi dosen</strong> dipakai untuk semua kuliah semester itu. Sebaiknya kuesionernya tidak diterbitkan sebagai survey umum agar tidak terisi dua kali.</p>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-field label="Semester" name="termId" required>
                        <select id="termId" wire:model="termId" class="input" @disabled($editingId)>
                            @foreach ($terms as $term)<option value="{{ $term->id }}">{{ $term->name }} · {{ $term->semester }}{{ $term->is_active ? ' (aktif)' : '' }}</option>@endforeach
                        </select>
                    </x-field>
                    <div class="sm:col-span-2">
                        <x-field label="Kuesioner" name="surveyId" required>
                            <select id="surveyId" wire:model="surveyId" class="input">
                                <option value="">Pilih kuesioner</option>
                                @foreach ($surveys as $survey)<option value="{{ $survey->id }}">{{ $survey->title }}</option>@endforeach
                            </select>
                        </x-field>
                    </div>
                    <div></div>
                    <x-field label="Dibuka" name="opens_on" required><input id="opens_on" type="date" wire:model="opens_on" class="input"></x-field>
                    <x-field label="Ditutup" name="closes_on" required><input id="closes_on" type="date" wire:model="closes_on" class="input"></x-field>
                    <div class="flex flex-col justify-center gap-1 sm:col-span-2">
                        <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" wire:model="gates_grades" class="size-5 accent-primary"> Wajib diisi sebelum mahasiswa melihat nilai (KHS) semester ini</label>
                        <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" wire:model="is_published" class="size-5 accent-primary"> Terbitkan (mahasiswa bisa mengisi sesuai tanggal)</label>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <x-button type="submit" wire:loading.attr="disabled" wire:target="save">{{ $editingId ? 'Simpan perubahan' : 'Buat periode' }}</x-button>
                    @if ($editingId)<x-button type="button" variant="secondary" wire:click="cancel">Batal</x-button>@endif
                </div>
            </x-card>
        </form>
    @endif

    <x-card :padding="false">
        @forelse ($periods as $period)
            <div class="flex min-h-14 flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0" wire:key="per-{{ $period->id }}">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ $period->academicTerm->name }} · {{ $period->academicTerm->semester }} — {{ $period->survey->title }}</p>
                    <p class="truncate text-xs text-text-muted">{{ $period->opens_on->translatedFormat('j M Y') }} – {{ $period->closes_on->translatedFormat('j M Y') }} · {{ $responses[$period->survey_id] ?? 0 }} evaluasi masuk{{ $period->gates_grades ? ' · syarat melihat nilai' : '' }}</p>
                </div>
                <x-badge :variant="$period->phaseVariant()">{{ $period->phaseLabel() }}</x-badge>
                @if ($canManage)<x-button size="sm" variant="secondary" wire:click="edit({{ $period->id }})">Ubah</x-button>@endif
            </div>
        @empty
            <x-empty-state icon="chart-bar" title="Belum ada periode evaluasi" description="Buat kuesioner Evaluasi dosen, lalu atur periodenya di sini." />
        @endforelse
    </x-card>
</div>
