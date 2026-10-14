<div class="space-y-6">
    <x-page-header title="Magang dan MBKM" subtitle="Ajukan magang atau program Merdeka Belajar, pantau persetujuan dan dosen pembimbingnya, lalu ajukan konversi SKS setelah selesai.">
        <x-slot:actions>
            <x-button :href="route('conversions.mine')" wire:navigate variant="secondary" icon="arrow-path">Konversi SKS</x-button>
        </x-slot:actions>
    </x-page-header>

    @error('internship')<p class="rounded-lg border border-danger/40 bg-danger/10 p-3 text-sm text-danger" role="alert">{{ $message }}</p>@enderror

    <form wire:submit="submit">
        <x-card title="Ajukan program">
            <p class="mb-3 text-sm {{ $credits >= $minCredits ? 'text-text-muted' : 'text-warning' }}">Syarat: SKS lulus minimal {{ $minCredits }} (kamu: {{ $credits }}) dan berstatus aktif.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Jenis program" name="type" required>
                    <select id="type" wire:model="type" class="input">@foreach ($types as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach</select>
                </x-field>
                <x-field label="Mitra / instansi" name="partner_name" required><input id="partner_name" type="text" wire:model="partner_name" class="input" @error('partner_name') aria-invalid="true" @enderror></x-field>
                <x-field label="Posisi / peran" name="position"><input id="position" type="text" wire:model="position" class="input"></x-field>
                <x-field label="Lokasi" name="location"><input id="location" type="text" wire:model="location" class="input"></x-field>
                <x-field label="Mulai" name="starts_on" required><input id="starts_on" type="date" wire:model="starts_on" class="input" min="{{ today()->toDateString() }}"></x-field>
                <x-field label="Selesai" name="ends_on" required><input id="ends_on" type="date" wire:model="ends_on" class="input"></x-field>
                <x-field label="Pembimbing lapangan" name="field_supervisor"><input id="field_supervisor" type="text" wire:model="field_supervisor" class="input"></x-field>
                <x-field label="SKS yang diharapkan" name="proposed_credits" required hint="Perkiraan SKS yang akan dikonversi nanti."><input id="proposed_credits" type="number" min="0" max="40" wire:model="proposed_credits" class="input text-right tabular-nums" inputmode="numeric"></x-field>
                <div class="sm:col-span-2"><x-field label="Uraian kegiatan" name="description"><textarea id="description" wire:model="description" rows="3" class="input"></textarea></x-field></div>
            </div>
            <div class="mt-4"><x-button type="submit" wire:loading.attr="disabled" wire:target="submit">Ajukan</x-button></div>
        </x-card>
    </form>

    <x-card title="Pengajuanku" :padding="false">
        @forelse ($internships as $internship)
            <div class="border-t border-border px-4 py-3 first:border-t-0" wire:key="int-{{ $internship->id }}">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="min-w-0 flex-1 text-sm font-medium">{{ $internship->partner_name }}@if ($internship->position) · {{ $internship->position }}@endif</p>
                    <x-badge :variant="$internship->status->variant()">{{ $internship->status->label() }}</x-badge>
                    @if ($internship->final_letter)<x-badge variant="success">Nilai {{ $internship->final_letter }}</x-badge>@endif
                </div>
                <p class="mt-0.5 text-xs text-text-muted">{{ $internship->type->label() }} · {{ $internship->periodLabel() }} · {{ $internship->proposed_credits }} SKS diharapkan @if ($internship->supervisor) · Pembimbing: {{ $internship->supervisor->name }}@endif</p>
                @if ($internship->decision_note)<p class="mt-1 text-xs {{ $internship->status === \App\Enums\InternshipStatus::Rejected ? 'text-danger' : 'text-text-muted' }}">Catatan prodi: {{ $internship->decision_note }}</p>@endif
                @if ($internship->evaluation_note)<p class="mt-1 text-xs text-text-muted">Evaluasi pembimbing: {{ $internship->evaluation_note }}</p>@endif
                @if (in_array($internship->status, [\App\Enums\InternshipStatus::Submitted, \App\Enums\InternshipStatus::Approved], true) && $internship->starts_on->isFuture())
                    <div class="mt-2"><x-button size="sm" variant="secondary" wire:click="cancel({{ $internship->id }})" wire:confirm="Batalkan pengajuan ini?">Batalkan</x-button></div>
                @endif
            </div>
        @empty
            <x-empty-state icon="building-office" title="Belum ada pengajuan" description="Magang atau program MBKM yang kamu ajukan muncul di sini." />
        @endforelse
    </x-card>
</div>
