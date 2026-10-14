<div class="space-y-6">
    <x-page-header title="Beasiswa" subtitle="Beasiswa yang dibuka kampus, syaratnya, dan status pendaftaranmu." />

    @foreach (['motivation', 'application'] as $key)@error($key)<p class="rounded-lg border border-danger/40 bg-danger/10 p-3 text-sm text-danger" role="alert">{{ $message }}</p>@enderror @endforeach

    <div class="space-y-4">
        @forelse ($scholarships as $scholarship)
            @php $application = $mine->get($scholarship->id); $registered = $application && $application->status !== \App\Enums\ApplicationStatus::Withdrawn; @endphp
            <x-card wire:key="sch-{{ $scholarship->id }}">
                <div class="flex flex-wrap items-start gap-3">
                    <div class="min-w-0 flex-1">
                        <h2 class="text-base font-semibold">{{ $scholarship->name }}</h2>
                        <p class="text-sm text-text-muted">{{ $scholarship->provider }}</p>
                    </div>
                    @if ($registered)
                        <x-badge :variant="$application->status->variant()">{{ $application->status->label() }}</x-badge>
                    @elseif ($scholarship->isOpen())
                        <x-badge variant="success">Dibuka s.d. {{ $scholarship->closes_on->translatedFormat('j M Y') }}</x-badge>
                    @else
                        <x-badge>Ditutup</x-badge>
                    @endif
                </div>
                @if ($scholarship->description)<p class="mt-2 whitespace-pre-line text-sm">{{ $scholarship->description }}</p>@endif
                <x-info-list class="mt-3" :items="array_filter([
                    'Besaran' => $scholarship->amount_per_term ? 'Rp'.number_format($scholarship->amount_per_term, 0, ',', '.').' per semester' : null,
                    'Kuota' => $scholarship->quota ? $scholarship->quota.' penerima ('.$scholarship->acceptedCount().' terisi)' : null,
                    'IPK minimal' => $scholarship->min_gpa ? number_format((float) $scholarship->min_gpa, 2, ',', '.') : null,
                    'Semester minimal' => $scholarship->min_semester,
                    'Persyaratan lain' => $scholarship->requirements,
                ])" />

                @if ($registered && $application->review_note)<p class="mt-3 text-sm">Catatan panitia: {{ $application->review_note }}</p>@endif

                @if (! $registered)
                    @if ($scholarship->blockers)
                        <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-warning">@foreach ($scholarship->blockers as $blocker)<li>{{ $blocker }}</li>@endforeach</ul>
                    @elseif ($applyingId === $scholarship->id)
                        <form wire:submit="apply" class="mt-4 space-y-3">
                            <x-field label="Motivasi mendaftar" name="motivation" required hint="Minimal 20 karakter. Ceritakan kebutuhan dan rencanamu."><textarea id="motivation" wire:model="motivation" rows="4" class="input" @error('motivation') aria-invalid="true" @enderror></textarea></x-field>
                            <div class="flex gap-2"><x-button type="submit" wire:loading.attr="disabled" wire:target="apply">Kirim pendaftaran</x-button><x-button variant="ghost" wire:click="$set('applyingId', null)">Batal</x-button></div>
                        </form>
                    @else
                        <div class="mt-4"><x-button size="sm" wire:click="startApply({{ $scholarship->id }})">Daftar</x-button></div>
                    @endif
                @elseif ($application->status === \App\Enums\ApplicationStatus::Submitted)
                    <div class="mt-4"><x-button size="sm" variant="secondary" wire:click="withdraw({{ $application->id }})" wire:confirm="Batalkan pendaftaran beasiswa ini?">Batalkan pendaftaran</x-button></div>
                @endif
            </x-card>
        @empty
            <x-card><x-empty-state icon="heart" title="Belum ada beasiswa dibuka" description="Beasiswa yang dibuka kampus akan muncul di sini." /></x-card>
        @endforelse
    </div>

    @if ($applications->isNotEmpty())
        <x-card title="Riwayat pendaftaranku" :padding="false">
            @foreach ($applications as $application)
                <div class="flex flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0" wire:key="sapp-{{ $application->id }}">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium">{{ $application->scholarship->name }}</p>
                        <p class="text-xs text-text-muted">Didaftarkan {{ $application->created_at->translatedFormat('j M Y') }} · IPK saat mendaftar {{ $application->gpa === null ? '–' : number_format((float) $application->gpa, 2, ',', '.') }}</p>
                    </div>
                    <x-badge :variant="$application->status->variant()">{{ $application->status->label() }}</x-badge>
                </div>
            @endforeach
        </x-card>
    @endif
</div>
