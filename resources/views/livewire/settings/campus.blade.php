<div class="mx-auto max-w-4xl space-y-6">
    <x-page-header title="Pengaturan kampus" subtitle="Identitas, aturan presensi, lokasi check-in, dan syarat wisuda. Bobot nilai ada di menu Nilai.">
        <x-slot:actions>
            <x-button :href="route('academic.terms')" wire:navigate variant="secondary" icon="calendar-days">Semester akademik</x-button>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="save" class="space-y-4">
        <x-card title="Identitas">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Nama kampus" name="name" required>
                    <input id="name" type="text" wire:model="name" class="input" @error('name') aria-invalid="true" @enderror>
                </x-field>
                <x-field label="WhatsApp helpdesk" name="helpdesk_whatsapp" hint="Tombol Hubungi Helpdesk memakai nomor ini. Kosongkan untuk memakai nomor bawaan sistem.">
                    <input id="helpdesk_whatsapp" type="tel" wire:model="helpdesk_whatsapp" class="input" inputmode="tel" placeholder="081234567800" @error('helpdesk_whatsapp') aria-invalid="true" @enderror>
                </x-field>
            </div>
        </x-card>

        <x-card title="Presensi">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Mode presensi" name="attendance_mode" required>
                    <select id="attendance_mode" wire:model="attendance_mode" class="input">
                        <option value="per_hour">Per jam pelajaran</option>
                        <option value="daily">Harian</option>
                        <option value="both">Harian dan per jam</option>
                    </select>
                </x-field>
                <x-field label="Minimal kehadiran (%)" name="min_attendance_percent" hint="Syarat mengikuti ujian." required>
                    <input id="min_attendance_percent" type="number" min="0" max="100" wire:model="min_attendance_percent" class="input text-right tabular-nums" inputmode="numeric">
                </x-field>
                <x-field label="Batas alpa berturut-turut" name="absent_threshold" hint="Peringatan muncul bila mencapai angka ini." required>
                    <input id="absent_threshold" type="number" min="1" wire:model="absent_threshold" class="input text-right tabular-nums" inputmode="numeric">
                </x-field>
                <x-field label="Pertemuan per mata kuliah" name="meetings_per_course" required>
                    <input id="meetings_per_course" type="number" min="1" wire:model="meetings_per_course" class="input text-right tabular-nums" inputmode="numeric">
                </x-field>
            </div>
        </x-card>

        <x-card title="Check-in dosen">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Check-in dibuka" name="checkin_opens" required><input id="checkin_opens" type="time" wire:model="checkin_opens" class="input"></x-field>
                <x-field label="Check-in ditutup" name="checkin_closes" required><input id="checkin_closes" type="time" wire:model="checkin_closes" class="input"></x-field>
                <x-field label="Lintang kampus" name="geofence_lat" required><input id="geofence_lat" type="text" wire:model="geofence_lat" class="input tabular-nums" inputmode="decimal"></x-field>
                <x-field label="Bujur kampus" name="geofence_lng" required><input id="geofence_lng" type="text" wire:model="geofence_lng" class="input tabular-nums" inputmode="decimal"></x-field>
                <x-field label="Radius (meter)" name="geofence_radius" hint="Check-in hanya sah di dalam radius ini." required>
                    <input id="geofence_radius" type="number" min="10" wire:model="geofence_radius" class="input text-right tabular-nums" inputmode="numeric">
                </x-field>
            </div>
        </x-card>

        <x-card title="Syarat wisuda">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="SKS lulus minimal" name="graduation_min_credits" required>
                    <input id="graduation_min_credits" type="number" min="1" wire:model="graduation_min_credits" class="input text-right tabular-nums" inputmode="numeric">
                </x-field>
                <x-field label="IPK minimal" name="graduation_min_gpa" required>
                    <input id="graduation_min_gpa" type="text" wire:model="graduation_min_gpa" class="input text-right tabular-nums" inputmode="decimal">
                </x-field>
            </div>
        </x-card>

        <x-button type="submit" wire:loading.attr="disabled" wire:target="save">Simpan pengaturan</x-button>
    </form>
</div>
