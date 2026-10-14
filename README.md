# Aksaraloka Academic

Sistem informasi akademik dan presensi untuk **universitas**: mobile-first, berbahasa Indonesia, mendukung mode terang dan gelap, dan multi-kampus (tenant).

Stack: Laravel 13, Livewire 4 (komponen kelas), Alpine.js, Tailwind CSS v4, GSAP (lazy), Pest, dan dompdf.

## Fitur

**Akademik**
- Data induk mahasiswa, dosen, fakultas, prodi, dan kelas (impor CSV mahasiswa).
- Jadwal kuliah Senin–Sabtu dengan deteksi bentrok, kelas paralel, dan ekspor.
- Presensi oleh dosen (per jam atau harian), check-in dosen berbasis lokasi, rekap kehadiran, dan izin.
- Kalender akademik, semester, kurikulum dan prasyarat, KRS (persetujuan dosen wali), perwalian, serta status mahasiswa (cuti dan aktif kembali).
- Nilai berbobot (tugas, UTS, UAS, kehadiran), kunci dan revisi nilai, KHS, IPS/IPK, dan transkrip PDF.
- Ujian dan kartu ujian, jurnal perkuliahan (BAP), dan evaluasi dosen (EDOM).

**Tugas akhir dan kelulusan**
- Tugas akhir: judul, pembimbing, log bimbingan, dan sidang.
- Yudisium: syarat lulus dicek otomatis dari transkrip, predikat kelulusan, dan Surat Keterangan Lulus. Dosen, Kaprodi, dan wakil dekan melihat; admin menetapkan.
- Wisuda berbasis yudisium, lengkap dengan berita acara.

**Layanan mandiri mahasiswa**
- Permohonan surat online (aktif kuliah, transkrip sementara, rekomendasi, izin penelitian) sebagai PDF bernomor dengan QR verifikasi publik.
- Aktivitas kemahasiswaan dan rekap SKPI.
- Beasiswa, magang/MBKM, dan konversi SKS (masuk ke transkrip dan IPK).

**Keuangan**
- Biaya Pendidikan: tagihan UKT/BPP, virtual account, konfirmasi dan verifikasi pembayaran, webhook VA, panduan pembayaran per bank, dan syarat lunas untuk KRS.

**Lainnya**
- Berita kampus, survey dan kuesioner, masukan dan komplain, serta tombol helpdesk WhatsApp.
- Peran: admin/BAAK, Kaprodi/pimpinan, wakil dekan akademik, dosen, petugas presensi, petugas keuangan, mahasiswa, dan orang tua.

## Menjalankan

Prasyarat: PHP 8.4 (ekstensi `gd`, `mbstring`, `dom`, `xml`, `pdo_sqlite`), Composer 2, dan Node.js 20 atau lebih baru. Laravel Herd menyajikan situs di `http://academic.test`.

```bash
composer install
npm install
cp .env.example .env        # lewati bila .env sudah ada
php artisan key:generate
php artisan migrate:fresh --seed
npm run build               # atau: npm run dev
```

> `migrate:fresh --seed` menghapus seluruh data. Pakai hanya di lingkungan pengembangan.

Sehari-hari: `composer run dev` (server, antrean, dan Vite), `php artisan test --compact` (Pest), dan `vendor/bin/pint` (gaya kode).

Setelah seeding, semua akun contoh memakai kata sandi `password`, mis. `admin@aksaraloka.test`, `kaprodi@aksaraloka.test`, `dosen@aksaraloka.test`, `mahasiswa@aksaraloka.test`, dan `keuangan@aksaraloka.test`. Daftar lengkap ada di panduan.

## Dokumentasi

| Dokumen | Isi |
| --- | --- |
| [PANDUAN.md](PANDUAN.md) | Menjalankan aplikasi, panduan pengguna per peran, pengaturan kampus, arsitektur, dan batasan |
| [context.md](context.md) | Keputusan produk dan konvensi yang tidak terbaca dari kode, serta jebakan yang pernah terjadi |
| [design-system.md](design-system.md) | Token warna, komponen, tata letak, dan aturan gerak |
| [CLAUDE.md](CLAUDE.md) / [AGENTS.md](AGENTS.md) | Pedoman untuk asisten AI (Laravel Boost) |

## Lisensi

Proyek ini dibangun di atas Laravel, yang berlisensi [MIT](https://opensource.org/licenses/MIT).
