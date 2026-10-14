# Konteks proyek: Aksaraloka Academic

Catatan untuk siapa pun (manusia atau asisten AI) yang melanjutkan proyek ini. Isinya keputusan dan konvensi yang **tidak terbaca langsung dari kode**. Cara memakai dan menjalankan aplikasi ada di [`PANDUAN.md`](PANDUAN.md); gaya visual di [`design-system.md`](design-system.md).

## Apa ini

Sistem informasi akademik dan presensi **universitas** (bukan sekolah), mobile-first, berbahasa Indonesia. Dibangun dari PRD "Aksaraloka Academic (+ Presensi)" yang awalnya menulis SMP/SMA/kampus; **arah akhirnya universitas saja**, jadi seluruh istilah dan data contoh memakai konteks kampus.

Stack: Laravel 13, Livewire 4 (komponen kelas), Alpine, Tailwind v4, GSAP lazy, Pest, dompdf. Dev di Laravel Herd (`http://academic.test`), SQLite.

## Keputusan produk yang sudah final

- **Presensi hanya dicatat dosen.** Mahasiswa tidak boleh absen sendiri. Pindai QR, mode QR, dan tabel `qr_tokens` sengaja dihapus. Jangan menambahkan jalur absen mandiri tanpa persetujuan.
- **Status kehadiran kampus: Hadir, Izin, Sakit, Alpa** (tanpa Terlambat). Izin dan sakit dihitung **tidak hadir** pada persentase.
- **Syarat ujian 75%** per mata kuliah. Status mahasiswa memakai proyeksi 16 pertemuan per semester (boleh tidak hadir 4 kali): Aman / Waspada (sisa toleransi ≤ 1) / Tidak memenuhi.
- **Hari kuliah Senin–Sabtu.** Kolom Sabtu selalu tampil di Jadwal.
- **Alamat:** kolom `address/city/province/postal_code` pada `students` berarti **alamat sesuai KTP**; alamat tinggal sekarang ada di kolom `domicile_*` (+ `is_domicile_same`, `residence_type`). Mahasiswa hanya boleh mengubah domisili. _Perantau_ = kota domisili ≠ kota KTP.
- **"Kelas"** = kelompok mahasiswa per prodi dan angkatan (model `StudyGroup`, nama tabel masih `study_groups`; kolom `homeroom_teacher_id` = **dosen wali**). Bukan kelas paralel per mata kuliah. KRS memilih `TeachingAssignment` (kuliah satu kelas pada satu semester), jadi "paket kelas" = penugasan milik kelas mahasiswa.
- **Penangani masukan/komplain dan pembaca hasil survey = admin dan Kaprodi/Pimpinan.** Wakil dekan akademik tidak termasuk.
- **Survey anonim tidak menyimpan `user_id`**; pengisian ganda dicegah lewat hash HMAC. Pertanyaan dikunci setelah ada responden.
- **Wisuda:** berita acara satu dokumen **per periode**, dibekukan (snapshot peserta) saat diterbitkan. SKS dan IPK diisi mahasiswa lalu diverifikasi admin (belum ada modul nilai).
- **KRS:** `enrollments` (satu per mahasiswa per semester; status draft, submitted, approved, rejected) dan `enrollment_items` (ke `teaching_assignments`). Edit hanya sah saat `AcademicCalendar::isOpen(Enrollment)`; batas SKS dari IPS semester sebelumnya (`EnrollmentService::CREDIT_LIMITS`). KRS **disetujui** menambah mahasiswa ke `GradingService::roster()`. Jadwal dan presensi belum mengikuti KRS (masih per kelas angkatan).
- **Keuangan:** peran baru `finance` (Petugas keuangan; masuk lewat tab Dosen). `invoices` (satu per mahasiswa per semester, `va_number` tetap per mahasiswa), `invoice_items`, `payments` (pending/confirmed/rejected), `fee_rates` (UKT per golongan, BPP per prodi), `students.ukt_group`. Status tagihan dihitung ulang dari pembayaran **terkonfirmasi** (`FinanceService::refresh`). Syarat KRS lunas ada di `FinanceService::krsClearance()` dan dipanggil `EnrollmentService`; kunci pengaturan `krs_requires_payment`. Webhook VA tanpa sesi, dikecualikan dari CSRF di `bootstrap/app.php`.
- **Terjemahan validasi:** `lang/id/validation.php` ditambahkan; sebelumnya pesan validasi default tampil sebagai kunci mentah (`validation.required`). Tambah kunci baru di sana bila memakai aturan lain.
- **Kurikulum:** `curricula` (per prodi dan rentang angkatan), `curriculum_subjects` (semester dan jenis wajib/pilihan; semester di sini mengalahkan `subjects.semester`), dan `subject_prerequisites` (prasyarat global per mata kuliah, lulus = hasil terbaik bukan E). `CurriculumService::forStudent()` memilih kurikulum aktif terbaru yang mencakup angkatan. Bila prodi belum punya kurikulum, KRS menawarkan semua mata kuliah prodi.
- **Kelas paralel:** tetap memakai `teaching_assignments` per kelas angkatan, ditambah `section` dan `capacity`. Peserta sebenarnya ditentukan `ClassRoster` (anggota kelas + KRS disetujui − anggota yang pindah seksi), dipakai nilai, presensi, dan jadwal mahasiswa (`ScheduleBoard` view `student`). `study_group_id` tetap wajib; kuliah terbuka murni tanpa kelas angkatan belum didukung.
- **Status mahasiswa:** `students.status` adalah status sekarang; `student_term_statuses` menyimpan riwayat per semester; `status_requests` untuk pengajuan cuti dan aktif kembali (cuti hanya saat agenda Leave di kalender dibuka). Hanya perubahan pada semester aktif mengubah `students.status`.
- **Perwalian:** `advising_notes` ditulis hanya oleh dosen wali kelas mahasiswa pada semester aktif.
- **Kalender akademik:** tabel `academic_events` hanya menyimpan agenda KRS, UTS, UAS, cuti, libur, dan lainnya. Perkuliahan (`starts_at`–`ends_at`) dan batas input nilai (`grading_closes_on`) tetap di `academic_terms` dan ditampilkan sebagai agenda turunan oleh `AcademicCalendar`, supaya hanya ada satu sumber kebenaran. Batas input nilai diedit di halaman Semester akademik, bukan di Pengaturan nilai.
- **Nilai:** komponen tugas, UTS, UAS, dan kehadiran (otomatis dari presensi) berbobot; huruf mutu A–E dengan angka mutu 4/3/2/1/0 dan batas di `Tenant::DEFAULT_SETTINGS`. Nilai baru tampil ke mahasiswa setelah dosen **mengunci** kelas. Setelah dikunci, perubahan hanya lewat revisi yang **disetujui Kaprodi (`principal`)**, bukan admin. Batas waktu input ada di `academic_terms.grading_closes_on` (+ perpanjangan per kelas oleh admin).
- **Nilai terbaik:** `AcademicRecord::best()` memilih angka mutu tertinggi (lalu nilai angka, lalu semester terbaru) untuk mata kuliah yang diremedial atau diulang. KHS, IPS, IPK, transkrip, dan syarat wisuda **hanya menghitung nilai terkunci**. Remedial adalah baris `grades` ber-`kind = remedial`.
- **Nilai awal:** `students.legacy_credits` dan `legacy_quality_points` menampung riwayat sebelum sistem dipakai (mahasiswa pindahan atau migrasi) dan ikut IPK dan SKS lulus.
- **Wisuda:** SKS lulus dan IPK **tidak lagi diisi manual**; `GraduationService::eligibility()` mengambilnya dari transkrip dan menyimpan snapshot pada pendaftaran.
- **Beranda (`/home`) = berita kampus** (banner + grid kartu), bukan dashboard. Dashboard harian ada di `/summary`, **tanpa menu di sidebar** (sengaja dihapus dari navigasi atas permintaan pemilik produk; halamannya tetap ada).
- **Halaman lebar penuh:** Beranda dan Jadwal memakai `#[Layout('layouts.app', ['fullWidth' => true])]`; halaman lain dibatasi `max-w-6xl`.

## Model data dan multi-tenant

- Hampir semua tabel punya `tenant_id`. Trait `App\Models\Concerns\BelongsToTenant` memasang `TenantScope` (membatasi query ke kampus pengguna yang login) dan mengisi `tenant_id` saat membuat record **hanya bila ada pengguna login**. Di seeder, tes, dan konsol, **isi `tenant_id` secara eksplisit**.
- Tabel memakai satu skema; pemisahan schema PostgreSQL di PRD (`core`, `academic`, `attend`) belum dilakukan.
- `Tenant::DEFAULT_SETTINGS` menyimpan bawaan pengaturan kampus; belum ada layar untuk mengubahnya.
- Peran: `admin`, `principal` (Kaprodi/Pimpinan), `curriculum` (Wakil dekan akademik), `teacher`, `duty` (Petugas presensi), `student`, `guardian`. "Dosen wali" adalah status kontekstual (`StudyGroup.homeroom_teacher_id`), bukan peran.
- Jadwal dan deteksi bentrok **dibatasi per semester** (`academic_term_id`); jangan membandingkan jadwal lintas semester.

## Konvensi

- **Bahasa:** semua teks antarmuka Bahasa Indonesia, nada ramah dan lugas ("Kamu belum mengumpulkan", "Simpan perubahan"). Identifier kode dan nama tabel berbahasa Inggris; rute berbahasa Indonesia (`/students`, `/schedule`).
- **Livewire:** komponen berbasis kelas di `app/Livewire/<Modul>` dengan view di `resources/views/livewire/<modul>`. Logika bisnis ada di `app/Services`, bukan di komponen. Otorisasi **dilapis**: middleware `role:` di rute **dan** dicek ulang di aksi Livewire.
- **Desain:** hanya token warna (`bg-surface`, `text-text-muted`, `bg-primary`, …), tanpa hex di komponen (pengecualian: dokumen PDF/cetak yang berdiri sendiri). Target sentuh ≥ 44 px, fokus terlihat, status tidak hanya dengan warna.
- **Gerak:** hanya `opacity`/`transform`, GSAP dimuat lazy lewat `resources/js/motion.js`, mati pada `prefers-reduced-motion` dan Save-Data, konten tidak pernah tertahan lebih dari 600 ms.
- **File privat** (poster berita, lampiran izin dan masukan) di disk `local`, disajikan lewat controller yang memeriksa hak akses.
- **Kerja:** jalankan `vendor/bin/pint` setelah mengubah PHP dan `php artisan test --compact` sebelum commit. Commit ditutup dengan baris `Co-Authored-By`. Repo: `https://github.com/Aksaraloka/academic`, cabang `main`.

## Jebakan yang pernah terjadi

- **`$slots` adalah nama variabel yang dipakai Livewire 4.** Jangan memberi nama data view `slots` (dulu membuat halaman Atur jadwal error); pakai `timeSlots`.
- **Kelas Tailwind dinamis** (`bg-{{ $variant }}/15`) tidak ikut di-build. Gunakan peta kelas eksplisit seperti `$tones` atau `$palette` di view.
- **Validasi Livewire membuang field tanpa aturan.** Field seperti `parents.*.id` harus punya aturan (`nullable|integer`) agar tetap ada di hasil `validate()`.
- **Kolom `date` di SQLite** tersimpan sebagai datetime; cari dengan `whereDate`, jangan `where('date', 'Y-m-d')`.
- **`$user->student`/`->teacher` bisa null** (akun belum tertaut). Selalu tangani, dan tampilkan keadaan kosong yang menjelaskan langkah berikutnya.
- **Rute literal harus didaftar sebelum rute berparameter** (mis. `/news/manage` sebelum `/news/{news}`).
- **Tes memakai data seeder yang acak sebagian.** Tes tidak boleh bergantung pada status acak; atur datanya dulu (lihat `setCourseAttendance()` di `AttendanceAnalyticsTest`).
- **Shell Bash di Windows** sering gagal mengurai heredoc panjang; untuk skrip penyunting, tulis ke berkas lalu jalankan.
- **dompdf** wajib ≥ 3.1.6 (versi lebih lama punya advisori keamanan). Jalankan `composer audit` setelah memperbarui dependensi.

## Yang belum ada

**ganti kata sandi dari profil** dan reset oleh admin; presensi offline; notifikasi WhatsApp otomatis; impor Excel dan ekspor Dapodik/PDDikti; foto profil; pemisahan schema PostgreSQL.
