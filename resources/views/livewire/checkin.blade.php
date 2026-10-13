<div class="mx-auto max-w-lg space-y-6"
     x-data="{
        busy: false,
        locate(action) {
            if (!navigator.geolocation) { $wire.locationFailed('Perangkat ini tidak mendukung lokasi.'); return; }
            this.busy = true;
            navigator.geolocation.getCurrentPosition(
                async (p) => {
                    if (action === 'in') { await $wire.checkIn(p.coords.latitude, p.coords.longitude, p.coords.accuracy); }
                    else { await $wire.checkOut(p.coords.latitude, p.coords.longitude); }
                    this.busy = false;
                },
                () => { this.busy = false; $wire.locationFailed('Lokasi tidak bisa dibaca. Izinkan akses lokasi lalu coba lagi.'); },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
            );
        }
     }">
    <x-page-header title="Check-in" :subtitle="now()->translatedFormat('l, j F Y')" />

    @unless ($hasProfile)
        <x-card><x-empty-state icon="identification" title="Akunmu belum tertaut ke data dosen" description="Minta admin menautkan akunmu agar bisa check-in." /></x-card>
    @else
        <x-card>
            <div class="flex items-center gap-3">
                <span class="flex size-12 items-center justify-center rounded-full {{ $checkin?->check_in_at ? 'bg-success/15 text-success' : 'bg-surface-muted text-text-muted' }}">
                    <x-icon name="map-pin" class="size-6" />
                </span>
                <div class="min-w-0">
                    <p class="font-semibold">
                        @if ($checkin?->check_out_at) Sudah pulang
                        @elseif ($checkin?->check_in_at) Sedang bertugas
                        @else Belum check-in
                        @endif
                    </p>
                    <p class="text-sm text-text-muted tabular-nums">
                        @if ($checkin?->check_in_at)Masuk {{ $checkin->check_in_at->format('H.i') }}@endif
                        @if ($checkin?->check_out_at) · Pulang {{ $checkin->check_out_at->format('H.i') }}@endif
                        @unless ($checkin?->check_in_at) Dibuka {{ $tenant->setting('checkin_opens') }}–{{ $tenant->setting('checkin_closes') }} @endunless
                    </p>
                </div>
            </div>

            @if ($checkin?->flagged)
                <p class="mt-3 flex items-center gap-2 text-sm text-warning"><x-icon name="exclamation-triangle" class="size-4" /> Lokasimu ditandai untuk ditinjau admin.</p>
            @endif

            @if ($error)
                <p class="mt-4 rounded-lg border border-danger bg-danger/15 p-3 text-sm text-danger" role="alert">{{ $error }}</p>
            @endif

            <div class="mt-5 flex flex-wrap gap-2">
                @if (! $checkin?->check_in_at)
                    <x-button class="w-full sm:w-auto" icon="map-pin" ::disabled="busy" @click="locate('in')">
                        <span x-show="!busy">Check-in sekarang</span><span x-show="busy" x-cloak>Membaca lokasi…</span>
                    </x-button>
                @elseif (! $checkin->check_out_at)
                    <x-button class="w-full sm:w-auto" variant="secondary" icon="arrow-right-start-on-rectangle" ::disabled="busy" @click="locate('out')">
                        <span x-show="!busy">Check-out</span><span x-show="busy" x-cloak>Membaca lokasi…</span>
                    </x-button>
                @endif
            </div>

            <p class="mt-4 text-xs text-text-muted">Lokasi hanya dibaca saat kamu menekan tombol, tidak dilacak terus-menerus. Check-in berhasil bila kamu berada dalam radius {{ $tenant->setting('geofence_radius') }} m dari kampus.</p>
        </x-card>
    @endunless
</div>
