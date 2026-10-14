# Panduan Aksaraloka Academic

Aksaraloka Academic adalah sistem informasi akademik dan presensi untuk **universitas**: data induk mahasiswa dan dosen, jadwal kuliah, presensi oleh dosen, rekap kehadiran, wisuda, survey, masukan dan komplain, serta berita kampus. Antarmukanya mobile-first, berbahasa Indonesia, dan mendukung mode terang dan gelap.

Dokumen ini terdiri dari tiga bagian:

1. [Menjalankan aplikasi](#1-menjalankan-aplikasi) — untuk pengembang dan pengelola server.
2. [Panduan pengguna per peran](#2-panduan-pengguna-per-peran) — untuk admin, pimpinan, dosen, mahasiswa, dan orang tua.
3. [Pengaturan, arsitektur, dan batasan](#3-pengaturan-arsitektur-dan-batasan) — untuk pengembang.

---

## 1. Menjalankan aplikasi

### Prasyarat

| Kebutuhan    | Versi                                                                                            |
| ------------ | ------------------------------------------------------------------------------------------------ |
| PHP          | 8.4 (ekstensi `mbstring`, `gd`, `dom`, `xml`, `pdo_sqlite`; `pdo_pgsql` bila memakai PostgreSQL) |
| Composer     | 2.x                                                                                              |
| Node.js      | 20 atau lebih baru (dikembangkan dengan 24)                                                      |
| Server lokal | Laravel Herd (situs tersedia di `http://academic.test`) atau `php artisan serve`                 |

### Instalasi

```bash
composer install
npm install
cp .env.example .env        # lewati bila .env sudah ada
php artisan key:generate
php artisan migrate:fresh --seed
npm run build               # atau: npm run dev
```

Pengembangan sehari-hari:

```bash
composer run dev            # server, antrean, dan Vite sekaligus
php artisan test --compact  # menjalankan seluruh tes (Pest)
vendor/bin/pint             # merapikan gaya kode PHP
```

> `migrate:fresh --seed` **menghapus seluruh data**. Pakai hanya di lingkungan pengembangan.

### Variabel lingkungan penting

| Variabel            | Fungsi                                                                |
| ------------------- | --------------------------------------------------------------------- |
| `APP_URL`           | Alamat aplikasi, mis. `http://academic.test`                          |
| `APP_TIMEZONE`      | Zona waktu (bawaan `Asia/Jakarta`)                                    |
| `APP_LOCALE`        | Bahasa (bawaan `id`)                                                  |
| `DB_CONNECTION`     | `sqlite` untuk pengembangan; ganti ke `pgsql` untuk produksi          |
| `HELPDESK_WHATSAPP` | Nomor WhatsApp helpdesk bawaan (lihat [Helpdesk](#helpdesk-whatsapp)) |

### Akun contoh

Setelah `--seed`, semua akun memakai kata sandi **`password`**.

| Peran                | Email                                                      |
| -------------------- | ---------------------------------------------------------- |
| Admin / BAAK         | `admin@aksaraloka.test`                                    |
| Kaprodi / Pimpinan   | `kaprodi@aksaraloka.test`                                  |
| Wakil dekan akademik | `akademik@aksaraloka.test`                                 |
| Dosen                | `dosen@aksaraloka.test`, `dosen2@aksaraloka.test`          |
| Petugas presensi     | `petugas@aksaraloka.test`                                  |
| Mahasiswa            | `mahasiswa@aksaraloka.test` (juga `mhs1@` sampai `mhs12@`) |
| Orang tua            | `ortu@aksaraloka.test`                                     |

Data contoh berisi Universitas Aksara Nusantara dengan 2 fakultas, 3 program studi, 2 kelas Teknik Informatika, semester aktif 2026/2027 Ganjil, delapan semester arsip (2022/2023 Ganjil sampai 2025/2026 Genap), sekitar 190 mahasiswa angkatan 2022–2026 pada tiga prodi dengan riwayat nilai terkunci, remedial, dan mata kuliah yang diulang (angkatan 2022 sudah mencapai 144 SKS), riwayat presensi lima minggu, 10 berita, 4 kuesioner, 5 tiket masukan, dan satu periode wisuda.

---

## 2. Panduan pengguna per peran

### Peran dan menu

| Peran                    | Menu utama                                                                                      |
| ------------------------ | ----------------------------------------------------------------------------------------------- |
| **Admin / BAAK**         | Beranda, Mahasiswa, Presensi, Jadwal, Dosen, Kelas, Prodi, Rekap, Izin, Wisuda, Survey, Masukan |
| **Kaprodi / Pimpinan**   | Beranda, Presensi (pantau), Rekap, Check-in, Jadwal, Wisuda, Izin, Survey (hasil), Masukan      |
| **Wakil dekan akademik** | Beranda, Jadwal (atur), Kelas, Rekap, Check-in, Wisuda                                          |
| **Dosen**                | Beranda, Jadwal, Presensi, Check-in, Rekap, Izin, Survey, Masukan                               |
| **Petugas presensi**     | Beranda, Presensi, Check-in, Jadwal                                                             |
| **Mahasiswa**            | Beranda, Presensi, Jadwal, Wisuda, Survey, Masukan                                   |
| **Orang tua**            | Beranda, Jadwal, Kehadiran, Survey, Masukan                                                     |

Semua peran juga punya menu **Survey**, **Masukan**, tombol **Hubungi Helpdesk** di bawah menu Masukan, dan **Profil** (di menu pengguna, pojok kanan atas).

Di HP, empat menu pertama tampil di bar bawah dan sisanya ada di **Lainnya**. Di desktop, sidebar bisa diciutkan dan grup Survey bisa dilipat.

### Masuk dan keluar

Buka `/masuk`, isi email dan kata sandi. Percobaan salah dibatasi 5 kali per menit. **Keluar** ada di menu pengguna. Pilihan tema (Terang, Gelap, Ikuti sistem) ada di ikon matahari/bulan di header, dan tahun ajaran aktif tampil di sebelahnya.

### Beranda (berita kampus)

`/beranda` menampilkan kabar terbaru kampus: banner utama yang bergeser otomatis, lalu kartu berita. Cari berita lewat kolom cari atau filter kategori (Program baru, Beasiswa, Akademik, Kegiatan, Prestasi, Wisuda, Pengumuman).

**Admin mengelola berita** lewat tombol **Kelola berita** (`/berita/kelola`):

- Isi judul, kategori, ringkasan, isi, dan unggah poster (JPG/PNG/WebP, maksimal 3 MB, ideal lanskap 16:9).
- Centang **Tampilkan di banner utama** agar berita masuk banner (maksimal 5 terbaru).
- Berita draf hanya terlihat admin dan tampil paling atas daftarnya.

### Ringkasan

`/ringkasan` (tidak ada di sidebar; buka lewat alamatnya) merangkum hari ini sesuai peran: jadwal hari ini, status presensi, kehadiran keseluruhan, dan (untuk admin dan pimpinan) kelas yang belum diisi serta dosen yang belum check-in.

### Profil

`/profil` menampilkan data diri. Untuk **mahasiswa** isinya lengkap:

- **Data pribadi:** nama, NIM, NIK (tersamar), tempat dan tanggal lahir, jenis kelamin, agama, kewarganegaraan.
- **Kontak**, **Alamat sesuai KTP**, dan **Alamat domisili** (dipisah untuk mahasiswa perantau; mahasiswa ditandai _Perantau_ bila kota domisili berbeda dari kota KTP).
- **Data akademik:** program studi, fakultas, angkatan, kelas, dosen wali, jalur masuk, asal sekolah.
- **Data orang tua/wali:** nama, pekerjaan, pendidikan, telepon, alamat.

Tombol **Ubah profil** (`/profil/ubah`) memungkinkan mahasiswa memperbarui telepon dan **alamat domisili**, serta mengganti kata sandi. Identitas, alamat KTP, dan data akademik hanya bisa diubah BAAK; ajukan koreksi lewat menu Masukan.

### Jadwal

`/jadwal` menampilkan jadwal kuliah mingguan **Senin sampai Sabtu**.

- **Desktop:** grid hari × sesi, hari ini disorot, kuliah yang sedang berlangsung diberi penanda.
- **HP:** tab hari dengan daftar kuliah per hari.
- **Semester:** pilih semester pada kolom **Semester** untuk melihat jadwal semester sebelumnya (arsip, hanya baca).
- **Staf** bisa memilih tampilan menurut Kelas, Dosen, atau Ruang. Mahasiswa dan orang tua otomatis melihat kelasnya pada semester terpilih.
- **Unduh PDF:** tombol di header mengunduh jadwal yang sedang tampil (A4 lanskap). Mahasiswa dan orang tua hanya bisa mengunduh jadwal kelas mereka.

**Mengatur jadwal** (admin dan wakil dekan akademik): klik **Atur jadwal** (`/jadwal/atur`).

1. Bila belum ada jam kuliah, klik **Buat jam standar** (Senin–Sabtu, 6 sesi per hari mulai pukul 07.00).
2. Tambah mata kuliah (kode, SKS, semester) dan ruang bila perlu.
3. Pilih kelas, dosen, mata kuliah, sesi, dan ruang, lalu simpan.
4. Sistem menolak penempatan yang **bentrok** pada semester yang sama: dosen mengajar dua kelas sekaligus, kelas punya dua kuliah, atau ruang terpakai ganda.

### Presensi

**Presensi hanya dicatat oleh dosen.** Mahasiswa tidak dapat mengisi presensi sendiri.

**Dosen** (`/presensi`):

1. Buka menu Presensi; daftar kuliah hari ini tampil.
2. Klik **Mulai presensi** pada kuliah yang berlangsung. Semua mahasiswa otomatis berstatus _Hadir_ (kecuali yang punya izin atau sakit yang sudah disetujui hari itu).
3. Ketuk nama mahasiswa untuk menandainya _Alpa_, atau pilih status lain (Izin, Sakit) dari pilihan di kanan.
4. **Simpan dan selesai** untuk menutup sesi, atau **Simpan sementara**.
5. Dosen bisa mengoreksi sampai akhir hari. Setelah itu hanya dosen wali dan admin yang bisa, dan koreksi tercatat di log audit.

Status kehadiran di kampus: **Hadir, Izin, Sakit, Alpa**.

**Admin** bisa melihat dan membuka sesi untuk jadwal mana pun; **pimpinan** hanya memantau.

**Mahasiswa** (`/presensi-saya`) melihat analitik kehadiran per mata kuliah semester aktif:

- Ringkasan: kehadiran keseluruhan, jumlah mata kuliah, jumlah yang perlu diwaspadai, total alpa.
- **Pie chart** distribusi kehadiran, ditambah mini pie di tiap mata kuliah.
- Perbandingan antar mata kuliah dengan garis batas syarat ujian.
- Kartu per mata kuliah: jumlah Hadir/Izin/Sakit/Alpa, sisa toleransi tidak hadir, status **Aman / Waspada / Tidak memenuhi**, riwayat pertemuan (huruf H/I/S/A), dan rincian per tanggal.

Status dihitung dari proyeksi: target 16 pertemuan per semester dan syarat kehadiran 75% berarti mahasiswa boleh tidak hadir 4 kali. Izin dan sakit dihitung tidak hadir.

### Nilai, KHS, dan transkrip

Menu **Nilai** adalah grup menu dengan isi berbeda per peran:

| Peran | Submenu |
| --- | --- |
| Dosen | Input nilai, Revisi nilai |
| Kaprodi / Pimpinan | Persetujuan revisi, Kelengkapan nilai |
| Admin / BAAK | Kelengkapan nilai, Revisi nilai, Pengaturan nilai |
| Wakil dekan akademik | Kelengkapan nilai |
| Mahasiswa dan orang tua | KHS, Transkrip |

**Dosen mengisi nilai** (`/nilai/input`):

1. Pilih kelas yang diampu, lalu **Input nilai**. Daftar mahasiswa tampil dengan kolom Tugas, UTS, UAS, Kehadiran, Nilai akhir, dan Huruf.
2. Isi nilai 0–100. **Kehadiran dihitung otomatis** dari presensi yang kamu catat. Nilai akhir dan huruf mutu (A–E) langsung dihitung dengan bobot yang berlaku (bawaan 30% tugas, 25% UTS, 35% UAS, 10% kehadiran). Bobot bisa diubah per kelas selama belum dikunci; jumlahnya harus 100.
3. **Simpan draf** kapan saja. Bila sudah lengkap, **Kunci nilai**: nilai tampil di KHS mahasiswa dan tidak bisa diubah langsung.
4. **Mahasiswa mengulang** ditambahkan lewat NIM pada kartu "Tambah mahasiswa mengulang".
5. **Batas waktu:** setelah tanggal batas semester, dosen tidak bisa lagi mengisi atau mengunci nilai, kecuali admin memperpanjangnya untuk kelas itu.

**Revisi nilai:** nilai yang sudah dikunci diubah lewat tombol **Revisi** (nilai baru + alasan minimal 10 karakter). **Kaprodi** menyetujui atau menolak (penolakan wajib beralasan). Persetujuan langsung memperbarui nilai; semua keputusan tercatat di log audit.

**Remedial:** untuk mahasiswa berhuruf yang berhak remedial (bawaan D dan E), dosen mengisi nilai remedial setelah nilai dikunci. **Aturan nilai terbaik:** untuk mata kuliah yang diremedial atau diulang di semester lain, KHS dan transkrip memakai hasil dengan angka mutu tertinggi.

**Mahasiswa dan orang tua** (`/nilai/khs`, `/nilai/transkrip`):

- **KHS** per semester: kode, mata kuliah, SKS, nilai, huruf, mutu, **IPS**, serta **IPK** dan SKS lulus kumulatif. Mata kuliah yang nilainya belum dikunci dosen ditandai belum dirilis.
- **Transkrip** seluruh semester dengan hasil terbaik tiap mata kuliah, **IPK**, dan **SKS lulus** (nilai D ke atas). Nilai awal (riwayat sebelum sistem dipakai) ikut dihitung.
- Keduanya bisa diunduh sebagai **PDF**. Orang tua dengan lebih dari satu anak memilih anak dari kolom pilihan.

**Admin / pimpinan** memantau di **Kelengkapan nilai** (kelas terkunci, draf, belum dimulai, dan terlambat) dan mengatur di **Pengaturan nilai**: bobot bawaan, batas huruf A–D, huruf yang berhak remedial, dan batas waktu input per semester. Transkrip mahasiswa tertentu dibuka lewat tombol **Transkrip** di daftar Mahasiswa. Nilai awal untuk mahasiswa pindahan diisi di formulir mahasiswa (SKS dan total mutu).

### Check-in dosen

`/check-in` (dosen, petugas, wakil dekan, pimpinan): tekan **Check-in sekarang** dan izinkan akses lokasi. Berhasil bila berada dalam radius kampus (bawaan 100 m) dan pada jam yang ditentukan (bawaan 05.30–16.00). Lokasi hanya dibaca saat tombol ditekan. Lokasi yang mencurigakan (akurasi GPS terlalu sempurna) **ditandai untuk ditinjau**, tidak langsung ditolak. Gunakan **Check-out** saat pulang.

### Izin

`/izin` (admin, pimpinan, dosen, petugas, wakil dekan):

- **Dosen wali** atau admin mencatat izin/sakit mahasiswa (dengan lampiran foto surat), langsung berstatus disetujui, dan terisi otomatis saat sesi presensi dibuka.
- **Dosen** mengajukan izin sendiri (izin, sakit, dinas, cuti); **admin atau pimpinan** menyetujui atau menolak.

### Rekap kehadiran

`/rekap`:

- **Dosen, admin, pimpinan:** pilih kelas, mata kuliah (opsional), dan bulan (kosong = satu semester). Tabel menampilkan jumlah per status dan **syarat ujian** (memenuhi bila ≥ 75%). Mahasiswa yang alpa berulang (≥ 3 hari) muncul di daftar tindak lanjut. **Ekspor CSV** tersedia.
- **Orang tua:** melihat kehadiran anak per mata kuliah.

### Data induk (admin)

| Menu                         | Fungsi                                                                                                                                                                                                                          |
| ---------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Mahasiswa** (`/mahasiswa`) | Cari dan filter mahasiswa. **Tambah mahasiswa** mengisi data pribadi, alamat KTP dan domisili, data akademik, serta orang tua/wali (Ayah, Ibu, Wali).                                                                           |
| **Impor**                    | Unggah CSV (kolom `nim, nama, jenis_kelamin, tanggal_lahir, alamat, telepon, prodi, angkatan, kelas`). Sistem menampilkan pratinjau dan baris yang gagal (NIM duplikat dan sebagainya) sebelum menyimpan. Templat bisa diunduh. |
| **Dosen** (`/dosen`)         | Daftar dan tambah dosen.                                                                                                                                                                                                        |
| **Prodi** (`/prodi`)         | Fakultas dan program studi.                                                                                                                                                                                                     |
| **Kelas** (`/kelas`)         | Buat kelas (kelompok mahasiswa per prodi dan angkatan), tentukan dosen wali, dan tempatkan mahasiswa. Riwayat penempatan tersimpan.                                                                                             |

Perubahan data mahasiswa dan koreksi presensi dicatat di log audit.

### Wisuda

`/wisuda` (**Pendaftaran wisuda**):

- **Mahasiswa:** melihat periode berjalan, status pendaftaran, dan persyaratan (SKS lulus minimal 144, IPK minimal 2,00). **SKS dan IPK dihitung otomatis dari transkrip**, tidak diisi manual, dan pendaftaran hanya bisa dikirim bila syarat terpenuhi. Klik **Daftar wisuda**, isi judul tugas akhir, tanggal sidang, dan ukuran toga. Pendaftaran yang ditolak bisa diajukan ulang.
- **Admin:** kelola periode di `/wisuda/periode` (tanggal buka/tutup, tanggal wisuda, tempat, kuota, terbitkan), lalu verifikasi pendaftar di **Pendaftar** (setujui, atau tolak dengan alasan).
- **Berita acara pendaftaran wisuda:** setelah semua pendaftar diverifikasi, admin mengisi nomor, tanggal, ketua panitia, dan pejabat pengesah, lalu **Terbitkan**. Daftar peserta dibekukan dan keputusan verifikasi dikunci. Versi cetak (A4, bisa disimpan sebagai PDF) tersedia bagi admin, pimpinan, dan wakil dekan.

### Survey

Menu **Survey** punya tiga submenu:

- **Beranda:** kuesioner yang perlu diisi beserta batas waktunya; admin dan pimpinan juga melihat tingkat respons.
- **Kuesioner:** daftar kuesioner untuk perannya. **Admin** membuat kuesioner (pertanyaan skala 1–5, pilih satu, pilih beberapa, atau teks), menentukan sasaran responden per peran, tanggal buka dan tutup, serta mode **anonim**. Pertanyaan **dikunci** setelah ada responden.
- **Bantuan:** tanya-jawab seputar pengisian.

Responden mengisi **sekali** dan jawaban tidak bisa diubah. Pada kuesioner anonim, jawaban tidak menyimpan identitas. **Admin dan pimpinan** melihat hasil (rata-rata, distribusi, jawaban teks) dan mengekspor CSV.

### Masukan dan komplain

`/masukan`: kirim **masukan** atau **komplain** dengan kategori, judul, isi, dan lampiran (opsional), boleh **anonim** (nama disembunyikan dari penanganan, tetapi pengirim tetap bisa membalas). Tiap pesan mendapat nomor tiket (mis. `MK-2026-0001`). **Admin dan pimpinan** menanggapi lewat percakapan dan mengubah status: _Baru, Diproses, Selesai, Ditutup_. Jumlah tiket baru tampil di samping menu. Pengiriman dibatasi 5 pesan per jam.

### Helpdesk WhatsApp

Tombol **Hubungi Helpdesk** di sidebar (dan di menu Lainnya pada HP) membuka WhatsApp dengan pesan awal berisi nama, peran, dan NIM pengirim. Tombol tidak tampil bila nomor belum diatur.

---

## 3. Pengaturan, arsitektur, dan batasan

### Pengaturan kampus (tenant)

Pengaturan disimpan pada kolom JSON `settings` tabel `tenants`. Nilai yang tidak diisi memakai bawaan di `App\Models\Tenant::DEFAULT_SETTINGS`.

| Kunci                             | Bawaan           | Fungsi                                                      |
| --------------------------------- | ---------------- | ----------------------------------------------------------- |
| `attendance_mode`                 | `per_hour`       | `per_hour`, `daily`, atau `both`                            |
| `geofence_lat`, `geofence_lng`    | −6.2, 106.816666 | Titik pusat kampus untuk check-in                           |
| `geofence_radius`                 | `100`            | Radius check-in (meter)                                     |
| `checkin_opens`, `checkin_closes` | `05:30`, `16:00` | Jam check-in dosen                                          |
| `absent_threshold`                | `3`              | Batas alpa berulang untuk tindak lanjut                     |
| `min_attendance_percent`          | `75`             | Syarat kehadiran untuk ujian                                |
| `meetings_per_course`             | `16`             | Target pertemuan per semester (dasar proyeksi)              |
| `graduation_min_credits`          | `144`            | SKS minimal pendaftaran wisuda                              |
| `graduation_min_gpa`              | `2.0`            | IPK minimal pendaftaran wisuda                              |
| `helpdesk_whatsapp`               | kosong           | Nomor WhatsApp helpdesk; kosong memakai `HELPDESK_WHATSAPP` |
| `grade_weights` | 30/25/35/10 | Bobot bawaan tugas, UTS, UAS, kehadiran (menu Pengaturan nilai) |
| `grade_scale` | A 80, B 70, C 60, D 50 | Batas minimal huruf mutu; di bawah D adalah E |
| `remedial_letters` | D, E | Huruf yang berhak remedial |

Belum ada layar pengaturan. Ubah lewat tinker, mis.:

```bash
php artisan tinker --execute 'App\Models\Tenant::first()->update(["settings" => array_merge(App\Models\Tenant::first()->settings ?? [], ["helpdesk_whatsapp" => "081234567800", "geofence_radius" => 150])]);'
```

Nomor helpdesk boleh ditulis `0812…`, `+62 812…`, atau `62812…`; sistem menormalkannya ke format internasional.

### Teknologi

Laravel 13, Livewire 4 (komponen berbasis kelas), Alpine.js, Tailwind CSS v4, GSAP (dimuat lazy), Pest untuk tes, dompdf untuk PDF, dan Heroicons (SVG salinan di `resources/svg`). Gaya visual mengikuti [`design-system.md`](design-system.md): satu aksen merah, token warna untuk mode terang dan gelap, target sentuh minimal 44 px, dan animasi hanya `opacity` dan `transform` (mati pada _reduced motion_).

### Struktur kode

| Lokasi                       | Isi                                                                                                                                                                                                                     |
| ---------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `app/Enums`                  | Peran, status kehadiran, status mahasiswa, kategori berita, dan sebagainya                                                                                                                                              |
| `app/Models`                 | Model Eloquent. Trait `BelongsToTenant` + `TenantScope` membatasi data ke kampus pengguna dan mengisi `tenant_id` otomatis                                                                                              |
| `app/Services`               | Logika bisnis: `GradingService`, `AcademicRecord` (KHS, IPS, IPK, transkrip), `AttendanceService`, `AttendanceReport`, `ScheduleBoard`, `ScheduleConflictDetector`, `TeacherCheckinService`, `GraduationService`, `SurveyService`, `FeedbackService`, `StudentImporter`, `AuditLogger` |
| `app/Livewire`               | Halaman per modul (`Attendance`, `Schedule`, `Students`, `Wisuda`, `Survey`, `Feedback`, `News`, `Profile`, …)                                                                                                          |
| `app/Http/Controllers`       | Unduhan dan file privat (PDF jadwal, CSV, poster, lampiran, cetak berita acara)                                                                                                                                         |
| `app/Support/Navigation.php` | Definisi menu per peran, termasuk grup menu                                                                                                                                                                             |
| `resources/views/components` | Komponen Blade: `x-button`, `x-card`, `x-badge`, `x-stat-tile`, `x-progress`, `x-pie-chart`, `x-icon`, dan lainnya                                                                                                      |
| `resources/js`               | `theme.js` (tema), `motion.js` (animasi GSAP)                                                                                                                                                                           |
| `tests/Feature`              | Tes fitur (Pest)                                                                                                                                                                                                        |

Beberapa prinsip yang dipakai:

- **Otorisasi berlapis:** middleware `role:` pada rute, lalu pemeriksaan ulang di aksi Livewire dan _scope_ query (pengguna lain mendapat 403 atau 404).
- **File privat** (poster, lampiran, foto surat) disimpan di disk `local` dan hanya disajikan lewat rute yang memeriksa hak akses.
- **Audit:** perubahan data mahasiswa, koreksi presensi, verifikasi wisuda, penerbitan berita acara, dan perubahan status tiket tercatat di tabel `audit_logs`.
- Nama tabel memakai satu skema (tanpa pemisahan schema PostgreSQL).

### Batasan yang diketahui

Hal-hal berikut **belum ada** atau perlu keputusan lanjutan:

- **Ganti kata sandi dari profil dan reset oleh admin.** Lupa kata sandi lewat email sudah tersedia (`/lupa-kata-sandi`); email dikirim lewat `MAIL_MAILER` (bawaan `log`, isi surat ada di `storage/logs`). Atur SMTP di `.env` untuk produksi.
- Layar pengaturan kampus dan aktivasi semester (saat ini lewat data atau tinker).
- KRS online dan persetujuan dosen wali; kuota kelas paralel.
- Presensi saat offline, notifikasi WhatsApp otomatis ke orang tua, dan presensi gerbang.
- Impor Excel (baru CSV), ekspor format Dapodik atau Neo Feeder PDDikti.
- Foto profil.
- Notifikasi saat nilai dirilis atau revisi diputuskan, dan impor nilai massal dari Excel.
- Mahasiswa mengulang masih ditambahkan dosen secara manual lewat NIM (belum ada KRS).
- Pemisahan schema PostgreSQL (`core`, `academic`, `attend`) sesuai PRD.

Fitur yang sengaja **tidak ada**: absen mandiri oleh mahasiswa (termasuk pindai QR). Presensi sepenuhnya dicatat oleh dosen.
