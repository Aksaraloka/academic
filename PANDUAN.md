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
| Petugas keuangan     | `keuangan@aksaraloka.test`                                 |
| Mahasiswa tingkat akhir (skripsi) | `skripsi@aksaraloka.test`                     |
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
| **Petugas keuangan**     | Beranda, Keuangan, Akademik (kalender), Survey, Masukan                                          |
| **Mahasiswa**            | Beranda, Presensi, Jadwal, Wisuda, Survey, Masukan                                   |
| **Orang tua**            | Beranda, Jadwal, Kehadiran, Survey, Masukan                                                     |

Semua peran juga punya menu **Survey**, **Masukan**, tombol **Hubungi Helpdesk** di bawah menu Masukan, dan **Profil** (di menu pengguna, pojok kanan atas).

Di HP, empat menu pertama tampil di bar bawah dan sisanya ada di **Lainnya**. Di desktop, sidebar bisa diciutkan dan grup Survey bisa dilipat.

### Masuk dan keluar

Buka `/login`, pilih tab **Mahasiswa**, **Orang tua**, atau **Dosen** (dosen, Kaprodi, wakil dekan, dan petugas), lalu isi email dan kata sandi. Akun yang memilih tab salah ditolak dengan petunjuk tab yang benar; **admin bisa masuk dari tab mana pun**. Tab terpilih ikut di URL (`/login?as=guardian`). Percobaan salah dibatasi 5 kali per menit. **Keluar** ada di menu pengguna. Pilihan tema (Terang, Gelap, Ikuti sistem) ada di ikon matahari/bulan di header, dan tahun ajaran aktif tampil di sebelahnya.

### Beranda (berita kampus)

`/home` menampilkan kabar terbaru kampus: banner utama yang bergeser otomatis, lalu kartu berita. Cari berita lewat kolom cari atau filter kategori (Program baru, Beasiswa, Akademik, Kegiatan, Prestasi, Wisuda, Pengumuman).

**Admin mengelola berita** lewat tombol **Kelola berita** (`/news/manage`):

- Isi judul, kategori, ringkasan, isi, dan unggah poster (JPG/PNG/WebP, maksimal 3 MB, ideal lanskap 16:9).
- Centang **Tampilkan di banner utama** agar berita masuk banner (maksimal 5 terbaru).
- Berita draf hanya terlihat admin dan tampil paling atas daftarnya.

### Ringkasan

`/summary` (tidak ada di sidebar; buka lewat alamatnya) merangkum hari ini sesuai peran: jadwal hari ini, status presensi, kehadiran keseluruhan, dan (untuk admin dan pimpinan) kelas yang belum diisi serta dosen yang belum check-in.

### Profil

`/profile` menampilkan data diri. Untuk **mahasiswa** isinya lengkap:

- **Data pribadi:** nama, NIM, NIK (tersamar), tempat dan tanggal lahir, jenis kelamin, agama, kewarganegaraan.
- **Kontak**, **Alamat sesuai KTP**, dan **Alamat domisili** (dipisah untuk mahasiswa perantau; mahasiswa ditandai _Perantau_ bila kota domisili berbeda dari kota KTP).
- **Data akademik:** program studi, fakultas, angkatan, kelas, dosen wali, jalur masuk, asal sekolah.
- **Data orang tua/wali:** nama, pekerjaan, pendidikan, telepon, alamat.

Tombol **Ubah profil** (`/profile/edit`) memungkinkan mahasiswa memperbarui telepon dan **alamat domisili**, serta mengganti kata sandi. Identitas, alamat KTP, dan data akademik hanya bisa diubah BAAK; ajukan koreksi lewat menu Masukan.

### Jadwal

`/schedule` menampilkan jadwal kuliah mingguan **Senin sampai Sabtu**.

- **Desktop:** grid hari × sesi, hari ini disorot, kuliah yang sedang berlangsung diberi penanda.
- **HP:** tab hari dengan daftar kuliah per hari.
- **Semester:** pilih semester pada kolom **Semester** untuk melihat jadwal semester sebelumnya (arsip, hanya baca).
- **Staf** bisa memilih tampilan menurut Kelas, Dosen, atau Ruang. Mahasiswa dan orang tua otomatis melihat kelasnya pada semester terpilih.
- **Unduh PDF:** tombol di header mengunduh jadwal yang sedang tampil (A4 lanskap). Mahasiswa dan orang tua hanya bisa mengunduh jadwal kelas mereka.

**Mengatur jadwal** (admin dan wakil dekan akademik): klik **Atur jadwal** (`/schedule/manage`).

1. Bila belum ada jam kuliah, klik **Buat jam standar** (Senin–Sabtu, 6 sesi per hari mulai pukul 07.00).
2. Tambah mata kuliah (kode, SKS, semester) dan ruang bila perlu.
3. Pilih kelas, dosen, mata kuliah, sesi, dan ruang, lalu simpan.
4. Sistem menolak penempatan yang **bentrok** pada semester yang sama: dosen mengajar dua kelas sekaligus, kelas punya dua kuliah, atau ruang terpakai ganda.

### Presensi

**Presensi hanya dicatat oleh dosen.** Mahasiswa tidak dapat mengisi presensi sendiri.

**Dosen** (`/attendance`):

1. Buka menu Presensi; daftar kuliah hari ini tampil.
2. Klik **Mulai presensi** pada kuliah yang berlangsung. Semua mahasiswa otomatis berstatus _Hadir_ (kecuali yang punya izin atau sakit yang sudah disetujui hari itu).
3. Ketuk nama mahasiswa untuk menandainya _Alpa_, atau pilih status lain (Izin, Sakit) dari pilihan di kanan.
4. **Simpan dan selesai** untuk menutup sesi, atau **Simpan sementara**.
5. Dosen bisa mengoreksi sampai akhir hari. Setelah itu hanya dosen wali dan admin yang bisa, dan koreksi tercatat di log audit.

Status kehadiran di kampus: **Hadir, Izin, Sakit, Alpa**.

**Admin** bisa melihat dan membuka sesi untuk jadwal mana pun; **pimpinan** hanya memantau.

**Mahasiswa** (`/my-attendance`) melihat analitik kehadiran per mata kuliah semester aktif:

- Ringkasan: kehadiran keseluruhan, jumlah mata kuliah, jumlah yang perlu diwaspadai, total alpa.
- **Pie chart** distribusi kehadiran, ditambah mini pie di tiap mata kuliah.
- Perbandingan antar mata kuliah dengan garis batas syarat ujian.
- Kartu per mata kuliah: jumlah Hadir/Izin/Sakit/Alpa, sisa toleransi tidak hadir, status **Aman / Waspada / Tidak memenuhi**, riwayat pertemuan (huruf H/I/S/A), dan rincian per tanggal.

Status dihitung dari proyeksi: target 16 pertemuan per semester dan syarat kehadiran 75% berarti mahasiswa boleh tidak hadir 4 kali. Izin dan sakit dihitung tidak hadir.

### Pengaturan kampus, semester, dan kalender akademik

- **Pengaturan kampus** (`/settings/campus`, admin, menu Pengaturan): nama kampus, WhatsApp helpdesk, mode presensi, minimal kehadiran, batas alpa, jumlah pertemuan, jam dan lokasi check-in dosen, serta syarat wisuda (SKS dan IPK minimal). Perubahan tercatat di log audit.
- **Semester akademik** (`/academic-terms`, admin): tambah semester (tahun ajaran `2026/2027`, Ganjil atau Genap, tanggal perkuliahan, batas input nilai), **Aktifkan** satu semester berjalan (semester lain otomatis nonaktif), **Kunci** semester selesai sebagai arsip. Semester aktif tidak bisa dikunci dan semester terkunci tidak bisa diaktifkan.
- **Kalender akademik** (`/academic-calendar`, semua peran): masa KRS, perkuliahan, UTS, UAS, batas input nilai, cuti akademik, libur, dan agenda lain per semester, lengkap dengan kartu "Sedang berlangsung" dan "Segera datang". Admin dan Wakil dekan akademik menambah, mengubah, dan menghapus agenda. **Perkuliahan** dan **batas input nilai** diambil dari data semester sehingga tidak ganda; batas input nilai inilah yang menutup input dosen (menu Nilai).

### KRS (Kartu Rencana Studi)

Alur: mahasiswa memilih mata kuliah, mengajukan ke dosen wali, dosen wali menyetujui atau menolak. KRS hanya bisa diisi **selama masa KRS di Kalender akademik**; di luar masa itu halaman hanya menampilkan status.

- **Mahasiswa** (`/enrollment`, menu KRS): daftar mata kuliah prodi yang dibuka semester aktif, dengan paket kelas sendiri sudah tercentang. Tersedia penghitung SKS dan peringatan jadwal bentrok. **Simpan draf** kapan saja, lalu **Ajukan ke dosen wali**. Pengajuan bisa ditarik selama belum diputuskan. Bila ditolak, alasannya tampil dan KRS bisa diubah lagi.
- **Batas SKS** mengikuti IPS semester sebelumnya: IPS 3,00 ke atas 24 SKS, 2,50–2,99 21 SKS, 2,00–2,49 18 SKS, di bawah 2,00 15 SKS; mahasiswa tanpa riwayat 20 SKS. Mata kuliah yang sudah lulus (di atas huruf remedial) tidak ditawarkan lagi, sedangkan yang bernilai D atau E bisa diulang. Satu mata kuliah hanya boleh di satu kelas.
- **Persetujuan** (`/enrollment/approvals`, menu Persetujuan KRS): dosen wali melihat mahasiswa perwaliannya; admin, Kaprodi, dan wakil dekan akademik melihat semuanya. Filter semester dan status, lihat daftar mata kuliah, **Setujui** atau **Tolak** (alasan wajib). Keputusan tercatat di log audit.
- **Dampak ke nilai, presensi, dan jadwal:** mahasiswa ber-KRS disetujui otomatis masuk daftar peserta di Input nilai dan sesi presensi kelas yang dipilihnya, termasuk dari kelas angkatan lain. Anggota kelas yang pindah ke seksi lain lewat KRS dikeluarkan dari daftar kelas asal. Halaman Jadwal mahasiswa dan orang tua mengikuti KRS yang disetujui (bila belum ada, mengikuti paket kelas angkatan).
- **Kelas paralel:** satu mata kuliah bisa dibuka di beberapa kelas sekaligus. Admin dan wakil dekan menentukan **seksi** (A, B, ...) dan **kuota KRS** saat menyusun jadwal (Jadwal, Atur jadwal). KRS menolak kelas yang penuh, dan sisa kuota tampil di daftar pilihan.
- Kode: `EnrollmentService`, `ClassRoster`, `AcademicCalendar::isOpen()`.

### Kurikulum

- **Kelola** (`/curricula`, admin, Kaprodi, wakil dekan akademik): pilih program studi, buat kurikulum (nama, angkatan mulai, angkatan akhir opsional), lalu tambahkan mata kuliah per semester sebagai **Wajib** atau **Pilihan**. Mata kuliah baru bisa dibuat langsung dari halaman ini. **Prasyarat** diatur per mata kuliah; kurikulum bisa dinonaktifkan.
- **Berlaku otomatis:** kurikulum aktif terbaru yang mencakup angkatan mahasiswa dipakai KRS. Hanya mata kuliah di kurikulum itu yang ditawarkan, dan mata kuliah dengan prasyarat belum lulus (hasil terbaik E tidak dihitung lulus) tidak bisa dipilih.
- **Mahasiswa** (`/my-curriculum`, menu Kurikulum): daftar mata kuliah per semester dengan status Lulus, Di KRS semester ini, Belum lulus, atau Belum diambil, serta SKS wajib yang tersisa.

### Perwalian dan bimbingan

- **Dosen wali** (`/advising`, menu Perwalian): daftar mahasiswa perwalian semester aktif, status KRS masing-masing, dan riwayat bimbingan. Dosen wali menambah dan mengubah **catatan bimbingan** (tanggal, topik, catatan, tindak lanjut). Admin, Kaprodi, dan wakil dekan bisa melihat seluruhnya tetapi tidak menambah catatan. Dari halaman Persetujuan KRS ada pintasan Bimbingan per mahasiswa.
- **Mahasiswa** (`/my-advising`): melihat nama dosen wali dan riwayat catatan bimbingannya.

### Tugas akhir

Alur: mahasiswa mengajukan judul, program studi menyetujui sekaligus menetapkan pembimbing, mahasiswa mencatat bimbingan, pembimbing menyetujui maju sidang, program studi menjadwalkan sidang dan mencatat hasilnya.

- **Mahasiswa** (`/my-thesis`, menu Tugas Akhir & Kelulusan): **ajukan judul** (SKS lulus minimal 100 secara bawaan), lalu setelah disetujui **catat bimbingan** (tanggal, pembimbing, topik, ringkasan, tindak lanjut). Papan *Persiapan sidang* menampilkan log terverifikasi (minimal 8), SKS minimal 120, dan persetujuan pembimbing utama; tombol **Ajukan sidang** aktif bila semuanya terpenuhi. Jadwal dan hasil sidang tampil di halaman yang sama.
- **Program studi** (`/thesis`; Kaprodi, wakil dekan akademik, admin): daftar tugas akhir dengan filter status. Di detail: **setujui judul** sekaligus pilih pembimbing utama dan pendamping (atau **tolak** dengan alasan), ubah pembimbing, **jadwalkan sidang** (tanggal, jam, ruang, ketua, penguji), dan **catat hasil** (lulus, lulus dengan revisi, atau tidak lulus beserta nilai). Pembimbing utama tidak boleh menjadi ketua atau penguji sidangnya sendiri, dan jadwal ditolak bila penguji atau ruang bentrok.
- **Dosen:** melihat mahasiswa yang dibimbing atau diuji, **memverifikasi** log bimbingan (atau meminta perbaikan dengan catatan), dan pembimbing utama menekan **Setujui maju sidang** setelah log cukup. Pembimbing utama juga bisa mencatat hasil sidang.
- **Hasil sidang:** lulus (termasuk dengan revisi) menyelesaikan tugas akhir dan mengisi nilai akhir; tidak lulus mengembalikannya ke bimbingan dan persetujuan sidang harus diminta lagi.
- Batas SKS, jumlah log, dan opsi *tugas akhir wajib lulus* diatur di Pengaturan kampus. Kode: `ThesisService`.

### Yudisium dan kelulusan

- **Syarat lulus dicek otomatis** (`YudisiumService::evaluate`) dari transkrip: status aktif, **SKS lulus** minimal (bawaan 144), **IPK** minimal (2,00), **tidak ada nilai E**, **nilai D maksimal 25%** dari SKS lulus, tugas akhir lulus sidang, dan tidak ada tunggakan pembayaran lewat jatuh tempo. Batas-batas ini bisa diubah di Pengaturan kampus.
- **Predikat:** *Dengan Pujian* (IPK minimal 3,51, tanpa nilai D, masa studi paling lama 4,5 tahun), *Sangat Memuaskan* (IPK minimal 2,76), atau *Memuaskan*.
- **Staf** (`/yudisium`; Kaprodi, wakil dekan, admin): buat yudisium (nama, tanggal, nomor SK), lihat calon (mahasiswa aktif yang tugas akhirnya lulus sidang) lengkap dengan rincian tiap syarat, **Pilih semua yang memenuhi syarat**, lalu **Tetapkan kelulusan**. Mahasiswa yang diluluskan berstatus **Lulus** dengan tanggal dan predikat tercatat. Rekap yudisium dan **Surat Keterangan Lulus** per mahasiswa tersedia sebagai PDF.
- **Mahasiswa** (`/my-graduation`, Status kelulusan): daftar syarat beserta nilainya sekarang, perkiraan predikat, dan setelah ditetapkan, unduhan Surat Keterangan Lulus.
- **Wisuda sekarang mengikuti yudisium:** pendaftaran wisuda hanya bisa oleh mahasiswa yang sudah dinyatakan lulus yudisium. SKS, IPK, **judul skripsi, dan tanggal sidang diambil otomatis** dari transkrip dan tugas akhir (tidak diisi manual).

### Ujian dan kartu ujian

- **Jadwal ujian** (`/exams`, menu Ujian & Evaluasi; admin dan wakil dekan mengelola, dosen dan Kaprodi melihat): pilih semester, jenis (UTS atau UAS), mata kuliah, tanggal, jam, dan ruang. Satu jadwal berlaku untuk **semua kelas** mata kuliah itu semester tersebut. Tanggal harus berada dalam masa UTS atau UAS di Kalender akademik, dan ditolak bila bentrok dengan ujian lain pada kelas atau ruang yang sama. Dosen hanya melihat ujian mata kuliahnya.
- **Peserta** (tombol Peserta di tiap jadwal): daftar mahasiswa kelas itu beserta kehadiran dan status **Berhak** atau **Tidak berhak** mengikuti ujian.
- **Kartu ujian** (`/my-exams`, mahasiswa dan orang tua): pilih UTS atau UAS. Kartu **terbit** bila status akademik aktif, tagihan semester lunas (bila kampus mewajibkan lunas), dan ada mata kuliah berjadwal dengan **kehadiran minimal 75%** (angka mengikuti pengaturan *Minimal kehadiran* di Pengaturan kampus). Mata kuliah dengan kehadiran di bawah syarat ditandai Tidak berhak dan tidak masuk PDF. Kehadiran dihitung dari pertemuan yang sudah dipresensi; mata kuliah tanpa presensi dianggap memenuhi syarat.
- Kode: `ExamService`.

### Evaluasi dosen (EDOM)

- **Periode** (`/evaluations/periods`; admin dan wakil dekan mengelola): satu periode per semester dengan satu kuesioner berkategori *Evaluasi dosen* (dibuat lewat Survey, sebaiknya tidak diterbitkan sebagai survey umum), tanggal buka dan tutup, dan opsi **wajib diisi sebelum melihat nilai**. Periode harus diterbitkan agar mahasiswa bisa mengisi.
- **Mahasiswa** (`/my-evaluations`): daftar mata kuliah semester aktif (anggota kelas atau KRS disetujui) dengan tombol **Isi evaluasi** per kelas. Jawaban **anonim** (tidak menyimpan identitas, hanya penanda agar tidak terisi dua kali) dan tidak bisa diubah.
- **Syarat melihat nilai:** bila opsi wajib aktif, KHS semester itu (tampilan dan PDF) tertahan sampai evaluasi semua mata kuliah selesai. Transkrip tidak terpengaruh.
- **Hasil** (`/evaluations/results`): rata-rata per pertanyaan dan komentar per kelas. Dosen hanya melihat kelasnya sendiri. Rekap baru tampil setelah minimal **3 responden** agar anonim.
- Kode: `EvaluationService`; respons memakai tabel survey dengan kolom `teaching_assignment_id`.

### Jurnal perkuliahan (BAP)

- **Dosen mengisi di halaman presensi:** kartu *Jurnal perkuliahan* berisi pokok bahasan, materi, dan catatan. **Simpan sementara** menyimpan jurnal bila pokok bahasan sudah diisi; **Simpan dan selesai** mewajibkan pokok bahasan untuk sesi kuliah (presensi harian tidak memakai jurnal).
- **Jurnal kuliah** (`/journals`): daftar kuliah semester dengan jumlah pertemuan yang jurnalnya sudah terisi. Detail per kuliah menampilkan materi tiap pertemuan beserta kehadiran; dosen pengampu bisa mengisi atau mengubah jurnal pertemuan sebelumnya. Mahasiswa melihat jurnal mata kuliahnya (hanya baca).
- **BAP PDF:** admin, Kaprodi, wakil dekan, dan dosen pengampu bisa mengunduh Berita Acara Perkuliahan (materi per pertemuan, kehadiran, dan tanda tangan).
- Kode: `JournalService`.

### Keuangan (Aksaraloka Pay)

Tagihan UKT dan BPP per semester, status pembayaran, riwayat pembayaran, dan syarat lunas untuk KRS. Jumlah uang disimpan dalam rupiah bulat.

- **Tarif** (`/finance/rates`, admin dan petugas keuangan): UKT per **golongan 1–8** untuk tiap prodi (atau satu tarif bawaan semua golongan) dan BPP per prodi. Golongan UKT mahasiswa diisi di formulir Mahasiswa.
- **Menerbitkan tagihan** (`/finance`): pilih semester, tanggal jatuh tempo, dan (opsional) prodi, lalu **Terbitkan tagihan**. Sistem membuat tagihan UKT + BPP untuk mahasiswa **aktif** yang belum punya tagihan semester itu (mahasiswa cuti, non-aktif, dan DO dilewati). Menjalankannya lagi tidak membuat tagihan ganda. Tiap tagihan mendapat nomor `INV-…` dan **virtual account** (awalan kampus + NIM).
- **Status tagihan:** Belum dibayar, Dibayar sebagian, Lunas, Dibatalkan, dengan penanda Lewat jatuh tempo. Halaman Keuangan menampilkan total ditagihkan, diterima, belum dibayar, dan pembayaran yang menunggu verifikasi, dengan filter semester, status, dan pencarian nama atau NIM.
- **Mahasiswa dan orang tua** (`/pay`, menu Aksaraloka Pay): daftar tagihan, sisa tagihan, rincian, cara pembayaran (rekening kampus dan nomor VA), dan riwayat. Mahasiswa menekan **Konfirmasi pembayaran** (jumlah, metode, tanggal, referensi, bukti foto atau PDF maksimal 3 MB). Konfirmasi berstatus menunggu sampai petugas keuangan memverifikasi; jumlahnya tidak boleh melebihi sisa tagihan yang belum dalam verifikasi. Orang tua hanya melihat.
- **Petugas keuangan** (akun `keuangan@`, juga admin): di detail tagihan (`/finance/invoices/{id}`) **Konfirmasi** atau **Tolak** (alasan wajib) pembayaran mahasiswa, **Catat pembayaran** langsung (tunai, transfer, VA, dompet digital), **Beri dispensasi KRS**, atau **Batalkan tagihan** (hanya bila belum ada pembayaran terkonfirmasi). Kaprodi dan pimpinan bisa melihat saja.
- **Panduan pembayaran** (`/pay/guide`, tombol di Aksaraloka Pay dan di detail tagihan): pilih **Virtual account** atau **Transfer bank**, lalu pilih bank (BCA, Mandiri, BNI, BRI, BSI, CIMB Niaga, Permata, BTN). Tiap bank menampilkan langkah lewat ATM, aplikasi mobile, dan internet banking, dengan nomor VA mahasiswa dan rekening kampus terisi otomatis, serta langkah konfirmasi setelah membayar. Teks panduan ada di `app/Support/PaymentGuide.php`; nama menu bank bisa berubah mengikuti versi aplikasinya.
- **PDF:** tagihan beserta riwayat pembayarannya bisa diunduh (jadi bukti lunas setelah lunas) oleh pemilik tagihan dan petugas.
- **Syarat KRS:** bila pengaturan *Tagihan harus lunas sebelum KRS* aktif (bawaan aktif, diatur di Pengaturan kampus), mahasiswa dengan tagihan semester itu yang belum lunas dan tanpa dispensasi tidak bisa mengisi atau mengajukan KRS; halaman KRS menampilkan sisa tagihan dan tautan ke Aksaraloka Pay. Semester tanpa tagihan terbit tidak menghalangi KRS.
- **Webhook virtual account:** penyedia pembayaran dapat memanggil `POST /pay/webhook` dengan JSON `{"va_number": "...", "amount": 1000000, "reference": "...", "paid_at": "2026-10-14"}` dan header `X-Pay-Signature` berisi `HMAC-SHA256(body, PAY_WEBHOOK_SECRET)`. Pembayaran dialokasikan ke tagihan terbuka yang paling dulu jatuh tempo, idempoten menurut `reference`, dan ditolak bila jumlah melebihi sisa tagihan. Isi `PAY_WEBHOOK_SECRET` di `.env`; tanpa rahasia, webhook menolak semua permintaan.
- Kode: `FinanceService`, model `Invoice`, `Payment`, `FeeRate`.

### Status mahasiswa, cuti, dan aktif kembali

- **Status** per mahasiswa: Aktif, Cuti akademik, Non-aktif, Mutasi, Lulus, Drop out (DO). Setiap perubahan pada semester aktif tercatat di **riwayat status per semester** dan log audit. Mahasiswa yang tidak aktif tidak bisa mengisi KRS dan tidak ikut sesi presensi baru.
- **Mahasiswa** (`/academic-status`, menu Status akademik): melihat status dan riwayat, **mengajukan cuti akademik** (hanya saat masa Pengajuan cuti akademik di Kalender akademik dibuka) atau **aktif kembali** (bila sedang cuti atau non-aktif), disertai alasan. Satu pengajuan tertunda pada satu waktu.
- **Staf** (`/academic-status/manage`, menu Status mahasiswa; admin, Kaprodi, wakil dekan): menyetujui atau menolak pengajuan (penolakan wajib beralasan), dan mencatat status manual (non-aktif, DO, lulus, dan lainnya) per semester lewat pencarian nama atau NIM.


### Nilai, KHS, dan transkrip

Menu **Nilai** adalah grup menu dengan isi berbeda per peran:

| Peran | Submenu |
| --- | --- |
| Dosen | Input nilai, Revisi nilai |
| Kaprodi / Pimpinan | Persetujuan revisi, Kelengkapan nilai |
| Admin / BAAK | Kelengkapan nilai, Revisi nilai, Pengaturan nilai |
| Wakil dekan akademik | Kelengkapan nilai |
| Mahasiswa dan orang tua | KHS, Transkrip |

**Dosen mengisi nilai** (`/grades/input`):

1. Pilih kelas yang diampu, lalu **Input nilai**. Daftar mahasiswa tampil dengan kolom Tugas, UTS, UAS, Kehadiran, Nilai akhir, dan Huruf.
2. Isi nilai 0–100. **Kehadiran dihitung otomatis** dari presensi yang kamu catat. Nilai akhir dan huruf mutu (A–E) langsung dihitung dengan bobot yang berlaku (bawaan 30% tugas, 25% UTS, 35% UAS, 10% kehadiran). Bobot bisa diubah per kelas selama belum dikunci; jumlahnya harus 100.
3. **Simpan draf** kapan saja. Bila sudah lengkap, **Kunci nilai**: nilai tampil di KHS mahasiswa dan tidak bisa diubah langsung.
4. **Mahasiswa mengulang** ditambahkan lewat NIM pada kartu "Tambah mahasiswa mengulang".
5. **Batas waktu:** setelah tanggal batas semester, dosen tidak bisa lagi mengisi atau mengunci nilai, kecuali admin memperpanjangnya untuk kelas itu.

**Revisi nilai:** nilai yang sudah dikunci diubah lewat tombol **Revisi** (nilai baru + alasan minimal 10 karakter). **Kaprodi** menyetujui atau menolak (penolakan wajib beralasan). Persetujuan langsung memperbarui nilai; semua keputusan tercatat di log audit.

**Remedial:** untuk mahasiswa berhuruf yang berhak remedial (bawaan D dan E), dosen mengisi nilai remedial setelah nilai dikunci. **Aturan nilai terbaik:** untuk mata kuliah yang diremedial atau diulang di semester lain, KHS dan transkrip memakai hasil dengan angka mutu tertinggi.

**Mahasiswa dan orang tua** (`/grades/khs`, `/grades/transcript`):

- **KHS** per semester: kode, mata kuliah, SKS, nilai, huruf, mutu, **IPS**, serta **IPK** dan SKS lulus kumulatif. Mata kuliah yang nilainya belum dikunci dosen ditandai belum dirilis.
- **Transkrip** seluruh semester dengan hasil terbaik tiap mata kuliah, **IPK**, dan **SKS lulus** (nilai D ke atas). Nilai awal (riwayat sebelum sistem dipakai) ikut dihitung.
- Keduanya bisa diunduh sebagai **PDF**. Orang tua dengan lebih dari satu anak memilih anak dari kolom pilihan.

**Admin / pimpinan** memantau di **Kelengkapan nilai** (kelas terkunci, draf, belum dimulai, dan terlambat) dan mengatur di **Pengaturan nilai**: bobot bawaan, batas huruf A–D, dan huruf yang berhak remedial (batas waktu input ada di Semester akademik). Transkrip mahasiswa tertentu dibuka lewat tombol **Transkrip** di daftar Mahasiswa. Nilai awal untuk mahasiswa pindahan diisi di formulir mahasiswa (SKS dan total mutu).

### Check-in dosen

`/check-in` (dosen, petugas, wakil dekan, pimpinan): tekan **Check-in sekarang** dan izinkan akses lokasi. Berhasil bila berada dalam radius kampus (bawaan 100 m) dan pada jam yang ditentukan (bawaan 05.30–16.00). Lokasi hanya dibaca saat tombol ditekan. Lokasi yang mencurigakan (akurasi GPS terlalu sempurna) **ditandai untuk ditinjau**, tidak langsung ditolak. Gunakan **Check-out** saat pulang.

### Izin

`/leaves` (admin, pimpinan, dosen, petugas, wakil dekan):

- **Dosen wali** atau admin mencatat izin/sakit mahasiswa (dengan lampiran foto surat), langsung berstatus disetujui, dan terisi otomatis saat sesi presensi dibuka.
- **Dosen** mengajukan izin sendiri (izin, sakit, dinas, cuti); **admin atau pimpinan** menyetujui atau menolak.

### Rekap kehadiran

`/reports`:

- **Dosen, admin, pimpinan:** pilih kelas, mata kuliah (opsional), dan bulan (kosong = satu semester). Tabel menampilkan jumlah per status dan **syarat ujian** (memenuhi bila ≥ 75%). Mahasiswa yang alpa berulang (≥ 3 hari) muncul di daftar tindak lanjut. **Ekspor CSV** tersedia.
- **Orang tua:** melihat kehadiran anak per mata kuliah.

### Data induk (admin)

| Menu                         | Fungsi                                                                                                                                                                                                                          |
| ---------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Mahasiswa** (`/students`) | Cari dan filter mahasiswa. **Tambah mahasiswa** mengisi data pribadi, alamat KTP dan domisili, data akademik, serta orang tua/wali (Ayah, Ibu, Wali).                                                                           |
| **Impor**                    | Unggah CSV (kolom `nim, nama, jenis_kelamin, tanggal_lahir, alamat, telepon, prodi, angkatan, kelas`). Sistem menampilkan pratinjau dan baris yang gagal (NIM duplikat dan sebagainya) sebelum menyimpan. Templat bisa diunduh. |
| **Dosen** (`/teachers`)         | Daftar dan tambah dosen.                                                                                                                                                                                                        |
| **Prodi** (`/programs`)         | Fakultas dan program studi.                                                                                                                                                                                                     |
| **Kelas** (`/groups`)         | Buat kelas (kelompok mahasiswa per prodi dan angkatan), tentukan dosen wali, dan tempatkan mahasiswa. Riwayat penempatan tersimpan.                                                                                             |

Perubahan data mahasiswa dan koreksi presensi dicatat di log audit.

### Wisuda

`/graduation` (**Pendaftaran wisuda**):

- **Mahasiswa:** melihat periode berjalan, status pendaftaran, dan persyaratan (SKS lulus minimal 144, IPK minimal 2,00). **SKS dan IPK dihitung otomatis dari transkrip**, tidak diisi manual, dan pendaftaran hanya bisa dikirim bila syarat terpenuhi. Klik **Daftar wisuda**, isi judul tugas akhir, tanggal sidang, dan ukuran toga. Pendaftaran yang ditolak bisa diajukan ulang.
- **Admin:** kelola periode di `/graduation/periods` (tanggal buka/tutup, tanggal wisuda, tempat, kuota, terbitkan), lalu verifikasi pendaftar di **Pendaftar** (setujui, atau tolak dengan alasan).
- **Berita acara pendaftaran wisuda:** setelah semua pendaftar diverifikasi, admin mengisi nomor, tanggal, ketua panitia, dan pejabat pengesah, lalu **Terbitkan**. Daftar peserta dibekukan dan keputusan verifikasi dikunci. Versi cetak (A4, bisa disimpan sebagai PDF) tersedia bagi admin, pimpinan, dan wakil dekan.

### Survey

Menu **Survey** punya tiga submenu:

- **Beranda:** kuesioner yang perlu diisi beserta batas waktunya; admin dan pimpinan juga melihat tingkat respons.
- **Kuesioner:** daftar kuesioner untuk perannya. **Admin** membuat kuesioner (pertanyaan skala 1–5, pilih satu, pilih beberapa, atau teks), menentukan sasaran responden per peran, tanggal buka dan tutup, serta mode **anonim**. Pertanyaan **dikunci** setelah ada responden.
- **Bantuan:** tanya-jawab seputar pengisian.

Responden mengisi **sekali** dan jawaban tidak bisa diubah. Pada kuesioner anonim, jawaban tidak menyimpan identitas. **Admin dan pimpinan** melihat hasil (rata-rata, distribusi, jawaban teks) dan mengekspor CSV.

### Masukan dan komplain

`/feedback`: kirim **masukan** atau **komplain** dengan kategori, judul, isi, dan lampiran (opsional), boleh **anonim** (nama disembunyikan dari penanganan, tetapi pengirim tetap bisa membalas). Tiap pesan mendapat nomor tiket (mis. `MK-2026-0001`). **Admin dan pimpinan** menanggapi lewat percakapan dan mengubah status: _Baru, Diproses, Selesai, Ditutup_. Jumlah tiket baru tampil di samping menu. Pengiriman dibatasi 5 pesan per jam.

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

- **Ganti kata sandi dari profil dan reset oleh admin.** Lupa kata sandi lewat email sudah tersedia (`/forgot-password`); email dikirim lewat `MAIL_MAILER` (bawaan `log`, isi surat ada di `storage/logs`). Atur SMTP di `.env` untuk produksi.
- Layar pengaturan kampus dan aktivasi semester (saat ini lewat data atau tinker).
- Payment gateway sungguhan (saat ini verifikasi manual dan webhook VA), cicilan terjadwal, denda keterlambatan, dan beasiswa atau keringanan UKT otomatis.
- Cetak KRS PDF, status otomatis "lulus" setelah wisuda, dan kuliah tanpa kelas angkatan (kelas terbuka murni lintas prodi).
- Presensi saat offline, notifikasi WhatsApp otomatis ke orang tua, dan presensi gerbang.
- Impor Excel (baru CSV), ekspor format Dapodik atau Neo Feeder PDDikti.
- Foto profil.
- Notifikasi saat nilai dirilis atau revisi diputuskan, dan impor nilai massal dari Excel.
- Pemisahan schema PostgreSQL (`core`, `academic`, `attend`) sesuai PRD.

Fitur yang sengaja **tidak ada**: absen mandiri oleh mahasiswa (termasuk pindai QR). Presensi sepenuhnya dicatat oleh dosen.
