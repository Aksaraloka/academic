<div class="space-y-6">
    <x-page-header title="Konversi SKS" subtitle="Ajukan mata kuliah yang ingin diakui dari magang atau MBKM, sertifikasi, maupun kuliah di kampus lain. Program studi yang menentukan nilainya.">
        <x-slot:actions>
            <x-button :href="route('grades.transcript')" wire:navigate variant="secondary" icon="document-text">Transkrip</x-button>
        </x-slot:actions>
    </x-page-header>

    @foreach (['conversion'] as $key)@error($key)<p class="rounded-lg border border-danger/40 bg-danger/10 p-3 text-sm text-danger" role="alert">{{ $message }}</p>@enderror @endforeach

    <form wire:submit="submit">
        <x-card title="Ajukan konversi">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Sumber konversi" name="source" required>
                    <select id="source" wire:model.live="source" class="input">@foreach ($sources as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach</select>
                </x-field>
                @if ($source === 'internship')
                    <x-field label="Magang / MBKM yang selesai" name="internshipId" required>
                        <select id="internshipId" wire:model="internshipId" class="input">
                            <option value="">Pilih program</option>
                            @foreach ($internships as $internship)<option value="{{ $internship->id }}">{{ $internship->partner_name }} · {{ $internship->periodLabel() }} (nilai {{ $internship->final_letter }})</option>@endforeach
                        </select>
                    </x-field>
                @endif
                <div class="sm:col-span-2">
                    <x-field label="Dasar konversi" name="description" required hint="Jelaskan kegiatan atau sertifikat yang jadi dasar pengajuan.">
                        <input id="description" type="text" wire:model="description" class="input" maxlength="300" @error('description') aria-invalid="true" @enderror>
                    </x-field>
                </div>
            </div>

            <fieldset class="mt-4">
                <legend class="text-sm font-medium">Mata kuliah yang diajukan <span class="text-danger" aria-hidden="true">*</span></legend>
                <p class="mt-1 text-sm {{ $selectedCredits > $maxCredits ? 'text-danger' : 'text-text-muted' }}">Dipilih {{ $selectedCredits }} SKS dari batas {{ $maxCredits }} SKS per pengajuan.</p>
                @error('subjectIds')<p class="mt-1 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
                <div class="mt-2 grid max-h-72 gap-1 overflow-y-auto rounded-lg border border-border p-2 sm:grid-cols-2">
                    @forelse ($subjects as $subject)
                        <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-md px-2 text-sm hover:bg-surface-muted" wire:key="cs-{{ $subject->id }}">
                            <input type="checkbox" wire:model.live="subjectIds" value="{{ $subject->id }}" class="size-5 accent-primary">
                            <span class="min-w-0 flex-1 truncate">{{ $subject->name }}</span>
                            <span class="text-xs tabular-nums text-text-muted">{{ $subject->credits }} SKS</span>
                        </label>
                    @empty
                        <p class="p-2 text-sm text-text-muted sm:col-span-2">Tidak ada mata kuliah yang tersedia untuk dikonversi.</p>
                    @endforelse
                </div>
            </fieldset>
            <div class="mt-4"><x-button type="submit" wire:loading.attr="disabled" wire:target="submit">Ajukan konversi</x-button></div>
        </x-card>
    </form>

    <x-card title="Pengajuanku" :padding="false">
        @forelse ($conversions as $conversion)
            <div class="border-t border-border px-4 py-3 first:border-t-0" wire:key="cv-{{ $conversion->id }}">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="min-w-0 flex-1 text-sm font-medium">{{ $conversion->source->label() }}@if ($conversion->internship) · {{ $conversion->internship->partner_name }}@endif</p>
                    <x-badge :variant="$conversion->status->variant()">{{ $conversion->status->label() }}</x-badge>
                </div>
                <p class="mt-0.5 text-xs text-text-muted">{{ $conversion->description }} · diajukan {{ $conversion->created_at->translatedFormat('j M Y') }}</p>
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($conversion->items as $item)
                        <li class="flex items-center gap-2"><span class="min-w-0 flex-1 truncate">{{ $item->subject->name }}</span><span class="text-xs tabular-nums text-text-muted">{{ $item->credits }} SKS</span>@if ($item->letter)<x-badge variant="success">{{ $item->letter }}</x-badge>@endif</li>
                    @endforeach
                </ul>
                @if ($conversion->review_note)<p class="mt-1 text-xs {{ $conversion->status === \App\Enums\ConversionStatus::Rejected ? 'text-danger' : 'text-text-muted' }}">Catatan prodi: {{ $conversion->review_note }}</p>@endif
                @if ($conversion->status === \App\Enums\ConversionStatus::Submitted)
                    <div class="mt-2"><x-button size="sm" variant="secondary" wire:click="cancel({{ $conversion->id }})" wire:confirm="Batalkan pengajuan ini?">Batalkan</x-button></div>
                @endif
            </div>
        @empty
            <x-empty-state icon="arrow-path" title="Belum ada pengajuan" description="Pengajuan konversi SKS dan hasil penilaiannya muncul di sini." />
        @endforelse
    </x-card>
</div>
