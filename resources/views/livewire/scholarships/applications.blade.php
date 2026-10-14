<div class="space-y-6">
    <x-page-header title="Seleksi pendaftar beasiswa" subtitle="Urut dari IPK tertinggi. Kuota dicek otomatis saat menerima pendaftar.">
        <x-slot:actions>
            <x-button :href="route('scholarships.manage')" wire:navigate variant="secondary" icon="arrow-left">Kelola beasiswa</x-button>
        </x-slot:actions>
    </x-page-header>

    @error('decision')<p class="rounded-lg border border-danger/40 bg-danger/10 p-3 text-sm text-danger" role="alert">{{ $message }}</p>@enderror

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-field label="Beasiswa" name="scholarshipId">
            <select id="scholarshipId" wire:model.live="scholarshipId" class="input">
                <option value="">Semua beasiswa</option>
                @foreach ($scholarships as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach
            </select>
        </x-field>
        <x-field label="Status" name="status">
            <select id="status" wire:model.live="status" class="input">
                <option value="">Semua status</option>
                @foreach ($statuses as $option)<option value="{{ $option->value }}">{{ $option->label() }} ({{ $counts[$option->value] ?? 0 }})</option>@endforeach
            </select>
        </x-field>
        <x-field label="Cari" name="search"><input id="search" type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="Nama atau NIM"></x-field>
    </div>

    @if ($reviewing)
        <x-card title="Tinjau pendaftar">
            <x-info-list :items="[
                'Mahasiswa' => $reviewing->student->name.' · '.$reviewing->student->nim,
                'Program studi' => $reviewing->student->program?->label(),
                'Beasiswa' => $reviewing->scholarship->name,
                'IPK saat mendaftar' => $reviewing->gpa === null ? null : number_format((float) $reviewing->gpa, 2, ',', '.'),
                'SKS lulus' => $reviewing->credits,
                'Status mahasiswa' => $reviewing->student->status->label(),
            ]" />
            <p class="mt-4 text-xs font-medium text-text-muted">Motivasi</p>
            <p class="mt-1 whitespace-pre-line text-sm">{{ $reviewing->motivation }}</p>
            <div class="mt-4 max-w-xl"><x-field label="Catatan" name="note" hint="Wajib diisi bila tidak menerima."><input id="note" type="text" wire:model="note" class="input" maxlength="500"></x-field></div>
            <div class="mt-4 flex flex-wrap gap-2">
                <x-button wire:click="accept" wire:confirm="Terima pendaftar ini sebagai penerima beasiswa?" wire:loading.attr="disabled" wire:target="accept">Terima</x-button>
                <x-button variant="secondary" wire:click="reject">Tidak diterima</x-button>
                <x-button variant="ghost" wire:click="$set('reviewingId', null)">Tutup</x-button>
            </div>
        </x-card>
    @endif

    <x-card :padding="false">
        @forelse ($applications as $application)
            <div class="flex min-h-14 flex-wrap items-center gap-3 border-t border-border px-4 py-3 first:border-t-0" wire:key="sapr-{{ $application->id }}">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ $application->student->name }} · {{ $application->student->nim }}</p>
                    <p class="truncate text-xs text-text-muted">{{ $application->scholarship->name }} · IPK {{ $application->gpa === null ? '–' : number_format((float) $application->gpa, 2, ',', '.') }} · {{ $application->credits }} SKS</p>
                </div>
                <x-badge :variant="$application->status->variant()">{{ $application->status->label() }}</x-badge>
                @if ($application->status === \App\Enums\ApplicationStatus::Submitted)<x-button size="sm" wire:click="review({{ $application->id }})">Tinjau</x-button>@endif
            </div>
        @empty
            <x-empty-state icon="heart" title="Tidak ada pendaftar" description="Pendaftar beasiswa muncul di sini." />
        @endforelse
    </x-card>
</div>
