<div class="mx-auto max-w-lg space-y-6"
     x-data="{
        scanning: false, stream: null, timer: null,
        async start() {
            if (!('BarcodeDetector' in window)) { return; }
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                this.$refs.video.srcObject = this.stream;
                await this.$refs.video.play();
                this.scanning = true;
                const detector = new BarcodeDetector({ formats: ['qr_code'] });
                this.timer = setInterval(async () => {
                    const codes = await detector.detect(this.$refs.video).catch(() => []);
                    if (codes.length) { this.stop(); $wire.set('code', codes[0].rawValue); $wire.submit(); }
                }, 400);
            } catch (e) { this.stop(); }
        },
        stop() {
            clearInterval(this.timer);
            this.stream?.getTracks().forEach((t) => t.stop());
            this.scanning = false;
        }
     }"
     x-on:beforeunload.window="stop()">
    <x-page-header title="Pindai QR presensi" subtitle="Arahkan kamera ke QR yang ditampilkan gurumu." />

    <x-card>
        <div x-show="scanning" x-cloak class="mb-4 overflow-hidden rounded-lg border border-border bg-black">
            <video x-ref="video" class="aspect-square w-full object-cover" playsinline muted></video>
        </div>
        <x-button class="w-full" icon="qr-code" x-show="!scanning" @click="start()">Buka kamera</x-button>
        <x-button class="w-full" variant="secondary" x-show="scanning" x-cloak @click="stop()">Tutup kamera</x-button>

        <form wire:submit="submit" class="mt-5 space-y-3">
            <x-field label="Atau ketik kode secara manual" name="code" hint="Kode ada di bawah QR gurumu.">
                <input id="code" type="text" wire:model="code" class="input font-mono" autocomplete="off" autocapitalize="off" @error('code') aria-invalid="true" @enderror>
            </x-field>
            <x-button type="submit" variant="secondary" wire:loading.attr="disabled" wire:target="submit">Kirim kode</x-button>
        </form>

        @if ($message)
            <p class="mt-4 rounded-lg border p-3 text-sm {{ $success ? 'border-success bg-success/15 text-success' : 'border-danger bg-danger/15 text-danger' }}" role="status">{{ $message }}</p>
        @endif
    </x-card>
</div>
