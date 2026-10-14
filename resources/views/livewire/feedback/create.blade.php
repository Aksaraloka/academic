<div class="space-y-6">
    <x-page-header title="Kirim masukan atau komplain" subtitle="Ceritakan dengan jelas agar bisa segera ditindaklanjuti.">
        <x-slot:actions>
            <x-button :href="route('feedback.index')" wire:navigate variant="secondary" icon="arrow-left">Kembali</x-button>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="save" class="space-y-4">
        <x-card title="Pesanmu">
            <div class="space-y-4">
                <fieldset>
                    <legend class="mb-1.5 text-sm font-medium">Jenis</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($types as $option)
                            <label class="flex min-h-14 items-center gap-3 rounded-lg border px-4 transition {{ $type === $option->value ? 'border-primary bg-primary/5' : 'border-border hover:bg-surface-muted' }}">
                                <input type="radio" wire:model.live="type" value="{{ $option->value }}" class="size-5 accent-primary">
                                <x-icon :name="$option->icon()" class="{{ $option === \App\Enums\FeedbackType::Complaint ? 'text-danger' : 'text-primary' }}" />
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold">{{ $option->label() }}</span>
                                    <span class="block text-xs text-text-muted">{{ $option === \App\Enums\FeedbackType::Complaint ? 'Keluhan atas layanan atau masalah' : 'Saran atau ide perbaikan' }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <x-field label="Kategori" name="category" required>
                    <select id="category" wire:model="category" class="input">
                        @foreach ($categories as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach
                    </select>
                </x-field>

                <x-field label="Judul" name="subject" required>
                    <input id="subject" type="text" wire:model="subject" class="input" maxlength="150" placeholder="Ringkas dalam satu kalimat" @error('subject') aria-invalid="true" @enderror>
                </x-field>

                <x-field label="Isi pesan" name="body" required hint="Sebutkan kapan, di mana, dan siapa yang terlibat bila perlu (minimal 20 karakter).">
                    <textarea id="body" wire:model="body" rows="6" class="input" maxlength="5000" @error('body') aria-invalid="true" @enderror></textarea>
                </x-field>

                <x-field label="Lampiran" name="attachment" hint="Opsional. Foto atau PDF, maksimal 3 MB.">
                    <input id="attachment" type="file" wire:model="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf" class="input">
                </x-field>

                <label class="flex min-h-11 items-start gap-2 text-sm">
                    <input type="checkbox" wire:model="is_anonymous" class="mt-0.5 size-5 shrink-0 accent-primary">
                    <span>
                        Kirim sebagai anonim
                        <span class="block text-xs text-text-muted">Namamu disembunyikan dari penanganan. Kamu tetap bisa melihat dan membalas dari akunmu.</span>
                    </span>
                </label>
            </div>
        </x-card>

        <div class="flex flex-wrap gap-2">
            <x-button type="submit" icon="paper-airplane" wire:loading.attr="disabled" wire:target="save,attachment">Kirim</x-button>
            <x-button :href="route('feedback.index')" wire:navigate variant="secondary">Batal</x-button>
        </div>
    </form>
</div>
