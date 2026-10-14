<div class="space-y-6">
    <x-page-header :title="$survey ? 'Ubah kuesioner' : 'Buat kuesioner'">
        <x-slot:actions>
            <x-button :href="route('survey.index')" wire:navigate variant="secondary" icon="arrow-left">Kembali</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($locked)
        <p class="rounded-lg border border-warning bg-warning/15 p-3 text-sm text-warning" role="status">
            Kuesioner ini sudah diisi responden, jadi pertanyaannya dikunci. Kamu masih bisa mengubah judul, tanggal, dan status terbit.
        </p>
    @endif

    <form wire:submit="save" class="space-y-4">
        <x-card title="Informasi">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-field label="Judul" name="title" required>
                        <input id="title" type="text" wire:model="title" class="input" placeholder="Evaluasi Layanan Akademik Semester Ganjil" @error('title') aria-invalid="true" @enderror>
                    </x-field>
                </div>
                <x-field label="Kategori" name="category" required>
                    <select id="category" wire:model="category" class="input">
                        @foreach ($categories as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach
                    </select>
                </x-field>
                <div class="hidden sm:block"></div>
                <div class="sm:col-span-2">
                    <x-field label="Deskripsi" name="description" hint="Tujuan survey dan estimasi waktu pengisian.">
                        <textarea id="description" wire:model="description" rows="2" class="input"></textarea>
                    </x-field>
                </div>
                <x-field label="Dibuka" name="opens_on" required><input id="opens_on" type="date" wire:model="opens_on" class="input"></x-field>
                <x-field label="Ditutup" name="closes_on" required><input id="closes_on" type="date" wire:model="closes_on" class="input"></x-field>

                <fieldset class="sm:col-span-2">
                    <legend class="mb-1 text-sm font-medium">Sasaran responden <span class="text-danger" aria-hidden="true">*</span></legend>
                    <div class="grid gap-x-4 sm:grid-cols-2">
                        @foreach ($roles as $role)
                            <label class="flex min-h-11 items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="audience" value="{{ $role->value }}" class="size-5 accent-primary"> {{ $role->label() }}
                            </label>
                        @endforeach
                    </div>
                    @error('audience')<p class="text-sm text-danger" role="alert">{{ $message }}</p>@enderror
                </fieldset>

                <label class="flex min-h-11 items-start gap-2 text-sm sm:col-span-2">
                    <input type="checkbox" wire:model="is_anonymous" class="mt-0.5 size-5 shrink-0 accent-primary" @disabled($locked)>
                    <span>Anonim <span class="block text-xs text-text-muted">Jawaban tidak dikaitkan dengan akun responden.</span></span>
                </label>
                <label class="flex min-h-11 items-start gap-2 text-sm sm:col-span-2">
                    <input type="checkbox" wire:model="is_published" class="mt-0.5 size-5 shrink-0 accent-primary">
                    <span>Terbitkan <span class="block text-xs text-text-muted">Responden bisa melihat dan mengisi pada rentang tanggal di atas.</span></span>
                </label>
            </div>
        </x-card>

        <section aria-labelledby="pertanyaan" class="space-y-3">
            <h2 id="pertanyaan" class="text-lg font-semibold">Pertanyaan</h2>
            @error('questions')<p class="text-sm text-danger" role="alert">{{ $message }}</p>@enderror

            @foreach ($questions as $index => $question)
                <x-card wire:key="question-{{ $index }}-{{ count($questions) }}">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold">Pertanyaan {{ $loop->iteration }}</p>
                            @unless ($locked)
                                <div class="flex gap-1">
                                    <x-button size="sm" variant="ghost" wire:click="moveQuestion({{ $index }}, -1)" :disabled="$loop->first"><x-icon name="arrow-up" class="size-4" /><span class="sr-only">Naikkan</span></x-button>
                                    <x-button size="sm" variant="ghost" wire:click="moveQuestion({{ $index }}, 1)" :disabled="$loop->last"><x-icon name="arrow-down" class="size-4" /><span class="sr-only">Turunkan</span></x-button>
                                    <x-button size="sm" variant="ghost" wire:click="removeQuestion({{ $index }})" :disabled="count($questions) === 1"><x-icon name="trash" class="size-4 text-danger" /><span class="sr-only">Hapus pertanyaan {{ $loop->iteration }}</span></x-button>
                                </div>
                            @endunless
                        </div>

                        <fieldset @disabled($locked) class="space-y-3">
                            <x-field label="Teks pertanyaan" name="questions.{{ $index }}.text" required>
                                <input id="questions.{{ $index }}.text" type="text" wire:model="questions.{{ $index }}.text" class="input" @error('questions.'.$index.'.text') aria-invalid="true" @enderror>
                            </x-field>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-field label="Jenis jawaban" name="questions.{{ $index }}.type">
                                    <select id="questions.{{ $index }}.type" wire:model.live="questions.{{ $index }}.type" class="input">
                                        @foreach ($types as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach
                                    </select>
                                </x-field>
                                <label class="flex min-h-11 items-center gap-2 self-end text-sm">
                                    <input type="checkbox" wire:model="questions.{{ $index }}.required" class="size-5 accent-primary"> Wajib dijawab
                                </label>
                            </div>
                            @if (\App\Enums\SurveyQuestionType::from($question['type'])->hasOptions())
                                <x-field label="Pilihan jawaban" name="questions.{{ $index }}.options" hint="Satu pilihan per baris, minimal dua.">
                                    <textarea id="questions.{{ $index }}.options" wire:model="questions.{{ $index }}.options" rows="4" class="input" @error('questions.'.$index.'.options') aria-invalid="true" @enderror></textarea>
                                </x-field>
                            @endif
                        </fieldset>
                    </div>
                </x-card>
            @endforeach

            @unless ($locked)
                <x-button variant="secondary" icon="plus" wire:click="addQuestion">Tambah pertanyaan</x-button>
            @endunless
        </section>

        <div class="flex flex-wrap gap-2">
            <x-button type="submit" wire:loading.attr="disabled" wire:target="save">Simpan perubahan</x-button>
            <x-button :href="route('survey.index')" wire:navigate variant="secondary">Batal</x-button>
        </div>
    </form>
</div>
