<div class="space-y-6">
    <x-page-header title="Pendaftaran wisuda" subtitle="Status pendaftaran, antrean verifikasi, dan berita acara.">
        @if (! $isStudent && auth()->user()->isAdmin())
            <x-slot:actions>
                <x-button :href="route('wisuda.periods')" wire:navigate icon="cog-6-tooth" variant="secondary">Kelola periode</x-button>
            </x-slot:actions>
        @endif
    </x-page-header>

    @if ($periods->count() > 1)
        <div>
            <label for="periode" class="sr-only">Periode wisuda</label>
            <select id="periode" wire:model.live="periodId" class="input sm:max-w-sm">
                @foreach ($periods as $option)
                    <option value="{{ $option->id }}" @selected($period?->id === $option->id)>{{ $option->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if (! $period)
        <x-card>
            <x-empty-state icon="academic-cap" title="Belum ada periode wisuda"
                :description="$isStudent ? 'Periode wisuda akan diumumkan oleh BAAK. Cek lagi nanti.' : 'Buat periode wisuda pertama agar mahasiswa bisa mendaftar.'">
                @if (! $isStudent && auth()->user()->isAdmin())
                    <x-button :href="route('wisuda.periods')" wire:navigate icon="plus">Buat periode</x-button>
                @endif
            </x-empty-state>
        </x-card>
    @else
        <section class="overflow-hidden rounded-card bg-brand text-brand-fg" aria-labelledby="periode-judul">
            <div class="relative p-5 sm:p-6">
                <span class="pointer-events-none absolute -right-10 -top-10 size-40 rounded-full bg-white/10" aria-hidden="true"></span>
                <div class="relative flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-medium opacity-80">Periode wisuda</p>
                        <h2 id="periode-judul" class="text-xl font-bold sm:text-2xl">{{ $period->name }}</h2>
                    </div>
                    <x-badge :variant="$period->phaseVariant()" class="!bg-white !text-black">{{ $period->phaseLabel() }}</x-badge>
                </div>
                <dl class="relative mt-4 grid gap-3 text-sm sm:grid-cols-3">
                    <div><dt class="text-xs opacity-80">Pendaftaran</dt><dd class="font-semibold">{{ $period->registration_opens_on->translatedFormat('j M') }} – {{ $period->registration_closes_on->translatedFormat('j M Y') }}</dd></div>
                    <div><dt class="text-xs opacity-80">Pelaksanaan wisuda</dt><dd class="font-semibold">{{ $period->ceremony_on->translatedFormat('l, j F Y') }}</dd></div>
                    <div><dt class="text-xs opacity-80">Tempat</dt><dd class="font-semibold">{{ $period->venue ?? 'Segera diumumkan' }}</dd></div>
                </dl>
            </div>
        </section>

        @if ($isStudent)
            <div class="grid gap-4 lg:grid-cols-2">
                <x-card title="Status pendaftaranku">
                    @if (! $student)
                        <x-empty-state icon="identification" title="Akunmu belum tertaut ke data mahasiswa" description="Minta BAAK menautkan akunmu agar bisa mendaftar wisuda." />
                    @elseif (! $registration)
                        <p class="text-sm text-text-muted">Kamu belum mendaftar di periode ini.</p>
                        @if ($period->isOpen())
                            <div class="mt-4"><x-button :href="route('wisuda.register', $period)" wire:navigate icon="academic-cap">Daftar wisuda</x-button></div>
                        @else
                            <p class="mt-2 text-sm font-medium text-warning">Pendaftaran tidak sedang dibuka ({{ $period->phaseLabel() }}).</p>
                        @endif
                    @else
                        <div class="flex flex-wrap items-center gap-2">
                            <x-badge :variant="$registration->status->variant()" :icon="$registration->status->icon()">{{ $registration->status->label() }}</x-badge>
                            <span class="text-xs text-text-muted">Diajukan {{ $registration->created_at->translatedFormat('j M Y') }}</span>
                        </div>
                        <p class="mt-3 text-sm font-medium">{{ $registration->thesis_title }}</p>
                        <p class="mt-1 text-xs text-text-muted tabular-nums">IPK {{ number_format((float) $registration->gpa, 2, ',', '.') }} · {{ $registration->credits_earned }} SKS</p>
                        @if ($registration->status === \App\Enums\GraduationStatus::Rejected)
                            <p class="mt-3 rounded-lg border border-danger bg-danger/15 p-3 text-sm text-danger" role="alert">Ditolak: {{ $registration->rejection_reason }}</p>
                            @if ($period->isOpen())
                                <div class="mt-3"><x-button :href="route('wisuda.register', $period)" wire:navigate icon="arrow-path">Ajukan ulang</x-button></div>
                            @endif
                        @elseif ($registration->status === \App\Enums\GraduationStatus::Approved)
                            <p class="mt-3 text-sm text-success">Pendaftaranmu disetujui. Informasi gladi dan toga akan menyusul dari panitia.</p>
                        @else
                            <p class="mt-3 text-sm text-text-muted">BAAK sedang memeriksa berkasmu.</p>
                        @endif
                    @endif
                </x-card>

                <x-card title="Persyaratan">
                    <ul class="space-y-2">
                        @foreach ($requirements as $requirement)
                            <li class="flex items-start gap-2 text-sm">
                                @if ($requirement['met'] === true)
                                    <x-icon name="check-circle" class="mt-0.5 text-success" /><span>{{ $requirement['label'] }}</span><span class="sr-only">(terpenuhi)</span>
                                @elseif ($requirement['met'] === false)
                                    <x-icon name="x-circle" class="mt-0.5 text-danger" /><span>{{ $requirement['label'] }}</span><span class="sr-only">(belum terpenuhi)</span>
                                @else
                                    <x-icon name="information-circle" class="mt-0.5 text-info" /><span>{{ $requirement['label'] }}</span><span class="sr-only">(diperiksa saat mendaftar)</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            </div>
        @else
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <x-stat-tile label="Pendaftar" :value="$counts['total']"
                    :note="$period->quota ? 'Kuota '.$period->quota.' · sisa '.max($period->quota - $counts['submitted'] - $counts['approved'], 0) : 'Tanpa batas kuota'" />
                <x-stat-tile label="Menunggu verifikasi" :value="$counts['submitted']" :note="$counts['submitted'] > 0 ? 'Perlu diperiksa' : 'Tidak ada antrean'" :tone="$counts['submitted'] > 0 ? 'text-warning' : 'text-success'" />
                <x-stat-tile label="Disetujui" :value="$counts['approved']" tone="text-success" note="Masuk berita acara" />
                <x-stat-tile label="Ditolak" :value="$counts['rejected']" :tone="$counts['rejected'] > 0 ? 'text-danger' : 'text-text-muted'" />
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <x-card title="Menunggu verifikasi" :padding="false">
                    @forelse ($pending as $row)
                        <div class="flex min-h-14 items-center gap-3 border-t border-border px-4 py-2.5 first:border-t-0 hover:bg-surface-muted" wire:key="pend-{{ $row->id }}">
                            <x-icon name="academic-cap" class="text-primary" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ $row->student->name }}</p>
                                <p class="truncate text-xs text-text-muted">{{ $row->student->nim }} · {{ $row->student->program?->label() ?? 'Tanpa prodi' }}</p>
                            </div>
                        </div>
                    @empty
                        <x-empty-state icon="check-circle" title="Tidak ada antrean verifikasi" description="Pendaftaran baru akan muncul di sini." />
                    @endforelse
                    <div class="border-t border-border p-4">
                        <x-button :href="route('wisuda.registrations', $period)" wire:navigate variant="secondary" size="sm" icon="clipboard-document-check">Semua pendaftar</x-button>
                    </div>
                </x-card>

                <div class="space-y-4">
                    <x-card title="Pendaftar per program studi">
                        @forelse ($byProgram as $program => $total)
                            <div class="mb-3 last:mb-0" wire:key="bp-{{ $loop->index }}">
                                <x-progress :label="$program" :value="$total" :max="$counts['submitted'] + $counts['approved']" />
                            </div>
                        @empty
                            <p class="text-sm text-text-muted">Belum ada pendaftar.</p>
                        @endforelse
                    </x-card>

                    <x-card title="Berita acara pendaftaran">
                        @if ($minute?->finalized_at)
                            <div class="flex flex-wrap items-center gap-2">
                                <x-badge variant="success" icon="check-circle">Diterbitkan</x-badge>
                                <span class="text-sm tabular-nums">{{ $minute->number }}</span>
                            </div>
                            <p class="mt-1 text-xs text-text-muted">{{ $minute->issued_on->translatedFormat('j F Y') }} · {{ count($minute->participants ?? []) }} peserta</p>
                        @else
                            <x-badge variant="warning" icon="clock">Belum diterbitkan</x-badge>
                            <p class="mt-2 text-sm text-text-muted">Terbitkan setelah semua pendaftaran selesai diverifikasi.</p>
                        @endif
                        <div class="mt-4"><x-button :href="route('wisuda.minutes', $period)" wire:navigate icon="document-text">Buka berita acara</x-button></div>
                    </x-card>
                </div>
            </div>
        @endif
    @endif
</div>
