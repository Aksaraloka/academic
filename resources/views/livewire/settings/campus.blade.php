<div class="space-y-6">
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

        <x-card title="Tugas akhir dan predikat kelulusan">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-field label="SKS minimal mengajukan judul" name="thesis_min_credits_propose" required><input id="thesis_min_credits_propose" type="number" min="0" wire:model="thesis_min_credits_propose" class="input text-right tabular-nums" inputmode="numeric"></x-field>
                <x-field label="SKS minimal maju sidang" name="thesis_min_credits_defense" required><input id="thesis_min_credits_defense" type="number" min="0" wire:model="thesis_min_credits_defense" class="input text-right tabular-nums" inputmode="numeric"></x-field>
                <x-field label="Log bimbingan minimal" name="thesis_min_guidance" hint="Yang sudah diverifikasi pembimbing." required><input id="thesis_min_guidance" type="number" min="0" wire:model="thesis_min_guidance" class="input text-right tabular-nums" inputmode="numeric"></x-field>
                <x-field label="Batas SKS bernilai D (%)" name="graduation_max_d_percent" hint="Dari total SKS lulus." required><input id="graduation_max_d_percent" type="text" wire:model="graduation_max_d_percent" class="input text-right tabular-nums" inputmode="decimal"></x-field>
                <x-field label="IPK Sangat Memuaskan" name="predicate_very_satisfactory_gpa" hint="Di bawahnya Memuaskan." required><input id="predicate_very_satisfactory_gpa" type="text" wire:model="predicate_very_satisfactory_gpa" class="input text-right tabular-nums" inputmode="decimal"></x-field>
                <x-field label="IPK Dengan Pujian" name="predicate_cum_laude_gpa" hint="Tanpa nilai D dan masa studi dalam batas." required><input id="predicate_cum_laude_gpa" type="text" wire:model="predicate_cum_laude_gpa" class="input text-right tabular-nums" inputmode="decimal"></x-field>
                <x-field label="Masa studi maksimal Dengan Pujian (tahun)" name="predicate_cum_laude_max_years" required><input id="predicate_cum_laude_max_years" type="text" wire:model="predicate_cum_laude_max_years" class="input text-right tabular-nums" inputmode="decimal"></x-field>
                <label class="flex min-h-11 items-center gap-2 text-sm sm:col-span-2 lg:col-span-3"><input type="checkbox" wire:model="thesis_required_for_graduation" class="size-5 accent-primary"> Tugas akhir wajib lulus sidang untuk dinyatakan lulus yudisium</label>
            </div>
        </x-card>

        <x-card title="Layanan mandiri mahasiswa">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field label="Nama penandatangan surat" name="letter_signatory_name" hint="Dicetak di bawah tanda tangan surat. Kosongkan bila ditandatangani manual."><input id="letter_signatory_name" type="text" wire:model="letter_signatory_name" class="input"></x-field>
                <x-field label="Jabatan penandatangan" name="letter_signatory_title" required><input id="letter_signatory_title" type="text" wire:model="letter_signatory_title" class="input"></x-field>
                <x-field label="SKS minimal magang / MBKM" name="internship_min_credits" hint="SKS lulus yang dibutuhkan untuk mengajukan." required><input id="internship_min_credits" type="number" min="0" wire:model="internship_min_credits" class="input text-right tabular-nums" inputmode="numeric"></x-field>
                <x-field label="Batas SKS per pengajuan konversi" name="conversion_max_credits" required><input id="conversion_max_credits" type="number" min="1" wire:model="conversion_max_credits" class="input text-right tabular-nums" inputmode="numeric"></x-field>
            </div>
        </x-card>

        <x-card title="Keuangan (Biaya Pendidikan)">
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="flex min-h-11 items-center gap-2 text-sm sm:col-span-2">
                    <input type="checkbox" wire:model="krs_requires_payment" class="size-5 accent-primary">
                    Tagihan harus lunas sebelum mahasiswa bisa mengisi KRS
                </label>
                <x-field label="Awalan virtual account" name="va_prefix" hint="Nomor VA = awalan + NIM."><input id="va_prefix" type="text" wire:model="va_prefix" class="input tabular-nums" inputmode="numeric" placeholder="8808"></x-field>
                <x-field label="Bank tujuan transfer" name="payment_bank_name"><input id="payment_bank_name" type="text" wire:model="payment_bank_name" class="input" placeholder="Bank Mandiri"></x-field>
                <x-field label="Nomor rekening" name="payment_account_number"><input id="payment_account_number" type="text" wire:model="payment_account_number" class="input tabular-nums" inputmode="numeric"></x-field>
                <x-field label="Atas nama" name="payment_account_name"><input id="payment_account_name" type="text" wire:model="payment_account_name" class="input"></x-field>
            </div>
        </x-card>

        <x-button type="submit" wire:loading.attr="disabled" wire:target="save">Simpan pengaturan</x-button>
    </form>
</div>
