# Aksaraloka Design System

Panduan visual dan pola komponen Aksaraloka (LMS perguruan tinggi, mobile-first, bahasa Indonesia). Tujuannya: aplikasi lain
yang memakai dokumen ini terlihat dan terasa sama. Sumber kebenaran kode ada di `resources/css/app.css` (token),
`resources/views/components/*` (komponen Blade), dan `resources/js/lib/*` (perilaku). Bila dokumen dan kode berbeda, kode yang benar.

## 1. Prinsip

1. **Mobile dulu.** Pengguna utama memakai HP Android dengan koneksi lambat. Tata letak dirancang untuk 390 px lebih dulu, lalu diperluas.
   Target halaman < 500 KB terkompresi dan < 3 detik di 3G.
2. **Satu aksen merah, sisanya netral.** Merah dipakai untuk aksi utama, tautan, dan penanda aktif. Status memakai warna semantik terpisah.
3. **Terang dan gelap setara.** Keduanya didukung penuh lewat token yang sama; gelap memakai dasar hitam murni.
4. **Token, bukan warna mentah.** Tidak ada hex di komponen. Semua warna lewat variabel CSS / utilitas token.
5. **Dapat diakses.** Kontras teks ≥ 4.5:1 (WCAG 2.1 AA), target sentuh ≥ 44 px, fokus terlihat, bisa dipakai dengan keyboard, menghormati `prefers-reduced-motion`.
6. **Ringan.** Pustaka berat dimuat lazy; animasi hanya `opacity` dan `transform`; konten tidak pernah tertahan oleh animasi.

## 2. Teknologi acuan

- Tailwind CSS v4 (`@theme`), dark mode berbasis class `.dark` pada `<html>`.
- Font **Plus Jakarta Sans** (variabel, self-host lewat `@fontsource-variable/plus-jakarta-sans`).
- Ikon **Heroicons** (outline 24 px, solid 16 px untuk badge).
- Alpine.js untuk interaksi ringan; SweetAlert2 untuk konfirmasi dan toast; Chart.js untuk grafik; GSAP (inti) untuk animasi halus.

## 3. Token warna

Nilai terang di `@theme`, nilai gelap di blok `.dark`. Gunakan lewat utilitas Tailwind (`bg-surface`, `text-text-muted`, `border-border`, `bg-primary`, ...).

| Token           | Terang    | Gelap     | Pemakaian                                             |
| --------------- | --------- | --------- | ----------------------------------------------------- |
| `bg`            | `#f8fafc` | `#000000` | Latar halaman                                         |
| `surface`       | `#ffffff` | `#0b0b0d` | Kartu, input, panel, modal                            |
| `surface-muted` | `#f1f5f9` | `#17171a` | Latar sekunder, hover baris, skeleton, jalur progress |
| `border`        | `#e2e8f0` | `#2c2c31` | Garis pemisah dan tepi kartu                          |
| `text`          | `#0f172a` | `#f4f4f5` | Teks utama                                            |
| `text-muted`    | `#475569` | `#a1a1aa` | Teks sekunder, label, keterangan                      |
| `primary`       | `#c62828` | `#ef4444` | Aksen: tombol utama, tautan, item aktif, fokus        |
| `primary-fg`    | `#ffffff` | `#000000` | Teks di atas `primary`                                |
| `brand`         | `#9f1f1f` | `#7f1d1d` | Panel merek (halaman masuk), banner                   |
| `brand-fg`      | `#ffffff` | `#ffffff` | Teks di atas `brand`                                  |
| `success`       | `#166534` | `#4ade80` | Berhasil, selesai, hadir                              |
| `warning`       | `#92400e` | `#fbbf24` | Peringatan, mendekati tenggat                         |
| `danger`        | `#991b1b` | `#f87171` | Galat, hapus, alpa                                    |
| `info`          | `#1d4ed8` | `#60a5fa` | Informasi, menunggu dinilai                           |

Aturan pakai:

- Latar tinted untuk badge dan penanda: warna semantik pada **15%** (`bg-success/15 text-success`). Pada gelap, teks pada `primary` berwarna hitam (`primary-fg`).
- Mode gelap: kartu dan input sedikit lebih terang dari latar hitam agar tetap terpisah (`surface` ≠ `bg`).
- Jangan menyandi informasi hanya dengan warna: selalu sertakan teks atau ikon (lihat status di bagian 7).
- Kontras semua pasangan teks/latar diuji otomatis (`tests/Feature/ThemeContrastTest.php`). Mengganti nilai token wajib lolos tes itu.

### Salin untuk aplikasi lain

```css
@import "tailwindcss";
@import "@fontsource-variable/plus-jakarta-sans";

@custom-variant dark (&:where(.dark, .dark *));

@theme static {
    --font-sans:
        "Plus Jakarta Sans Variable", ui-sans-serif, system-ui, sans-serif;
    --color-bg: #f8fafc;
    --color-surface: #ffffff;
    --color-surface-muted: #f1f5f9;
    --color-border: #e2e8f0;
    --color-text: #0f172a;
    --color-text-muted: #475569;
    --color-primary: #c62828;
    --color-primary-fg: #ffffff;
    --color-brand: #9f1f1f;
    --color-brand-fg: #ffffff;
    --color-success: #166534;
    --color-warning: #92400e;
    --color-danger: #991b1b;
    --color-info: #1d4ed8;
    --radius-card: 0.75rem;
}

.dark {
    color-scheme: dark;
    --color-bg: #000000;
    --color-surface: #0b0b0d;
    --color-surface-muted: #17171a;
    --color-border: #2c2c31;
    --color-text: #f4f4f5;
    --color-text-muted: #a1a1aa;
    --color-primary: #ef4444;
    --color-primary-fg: #000000;
    --color-brand: #7f1d1d;
    --color-brand-fg: #ffffff;
    --color-success: #4ade80;
    --color-warning: #fbbf24;
    --color-danger: #f87171;
    --color-info: #60a5fa;
}

@layer base {
    html {
        font-size: 16px;
        -webkit-text-size-adjust: 100%;
    }
    body {
        @apply bg-bg font-sans text-text antialiased;
    }
    :focus-visible {
        @apply outline-2 outline-offset-2 outline-primary;
    }
    button:not(:disabled),
    [role="button"]:not([aria-disabled="true"]),
    [role="menuitem"],
    [role="radio"],
    summary,
    label:has(> input[type="checkbox"]),
    label:has(> input[type="radio"]),
    input[type="checkbox"],
    input[type="radio"],
    input[type="submit"]:not(:disabled),
    select:not(:disabled) {
        cursor: pointer;
    }
    button:disabled,
    [aria-disabled="true"] {
        cursor: not-allowed;
    }
    [x-cloak] {
        display: none !important;
    }
    @media (prefers-reduced-motion: reduce) {
        *,
        *::before,
        *::after {
            animation-duration: 0.01ms !important;
            transition-duration: 0.01ms !important;
        }
    }
}

@layer components {
    .input {
        @apply block min-h-11 w-full rounded-lg border border-border bg-surface px-3 py-2 text-base text-text placeholder:text-text-muted;
    }
    .input[aria-invalid="true"] {
        @apply border-danger;
    }
    .input:disabled {
        @apply cursor-not-allowed bg-surface-muted opacity-70;
    }
    .skeleton {
        @apply animate-pulse rounded-md bg-surface-muted;
    }
}
```

Skrip anti-kedip tema (taruh di `<head>` sebelum CSS): baca `localStorage._x_theme` (`light` | `dark` | `system`), lalu
`document.documentElement.classList.toggle('dark', …)` dan `style.colorScheme`. Setelah navigasi halaman tanpa reload (SPA-like), terapkan lagi tema.

## 4. Tipografi

Satu keluarga: **Plus Jakarta Sans**, bobot 400, 500, 600, 700 (800 hanya untuk angka besar di rancangan).

| Peran                            | Kelas                                                     | Contoh              |
| -------------------------------- | --------------------------------------------------------- | ------------------- |
| Judul halaman (mobile / desktop) | `text-2xl font-bold` / `sm:text-3xl`                      | "Selamat datang, …" |
| Judul bagian                     | `text-lg font-semibold`                                   | "Mata kuliah saya"  |
| Judul kartu                      | `text-base font-semibold`                                 | Header kartu        |
| Isi                              | `text-base` (16 px), `leading-relaxed` untuk teks panjang | Konten materi       |
| Teks sekunder                    | `text-sm text-text-muted`                                 | Keterangan, tanggal |
| Label / meta                     | `text-xs text-text-muted`, `font-medium`                  | Label angka, badge  |
| Angka besar (ringkasan)          | `text-2xl sm:text-3xl font-bold leading-none`             | Tile statistik      |

Aturan: ukuran isian minimal 16 px (mencegah zoom otomatis di iOS), teks panjang tidak dibuat lebih sempit dari 16 px, gunakan `truncate` + `min-w-0` pada baris daftar, angka tabel memakai `tabular-nums`.

## 5. Bentuk, jarak, dan layout

- **Radius:** kartu `rounded-card` (0.75 rem); tombol, input, dan menu `rounded-lg` (0.5 rem); lencana dan progress `rounded-full`; modal di HP `rounded-t-2xl`.
- **Garis:** `border border-border` (1 px). Bayangan sangat jarang: `shadow-sm` hanya pada menu dropdown, `shadow-lg` pada panel melayang.
- **Jarak:** skala Tailwind 4 px. Ritme standar: isi kartu `p-4 sm:p-5`; antar kartu `space-y-4`; antar bagian halaman `space-y-6`; grid kartu `gap-4`; grup tombol `gap-2`.
- **Target sentuh:** minimal 44 px (`min-h-11`); baris daftar `min-h-12` (14 untuk baris dengan dua baris teks).
- **Lebar konten:** halaman aplikasi **lebar penuh** (`w-full px-4 sm:px-6`) di samping sidebar; tidak ada batas `max-w-*` pada halaman. Halaman masuk dan verifikasi publik berdiri sendiri dan tetap sempit.
- **Kerangka aplikasi:**
    - Desktop (`lg`): sidebar tetap kiri lebar `w-64` (ciut `w-[4.5rem]`), header atas `h-16` lengket dengan `bg-surface/90 backdrop-blur`. Menu dikelompokkan dalam kategori (Utama, Akademik, Layanan, Lainnya) dengan judul `text-[11px] font-semibold uppercase tracking-wide text-text-muted`; saat sidebar ciut judulnya berganti garis `border-t border-border`.
    - HP: tanpa sidebar; header yang sama dan **bar navigasi bawah** (maks. 5 item, `min-h-14`), konten diberi `pb-28`.
    - Header kanan: pemilih tema, lonceng notifikasi (lencana belum dibaca), menu pengguna (foto atau inisial).
- **Breakpoint:** `sm` 640, `lg` 1024 (titik pindah sidebar), `xl` 1280.
- **Grid kartu mata kuliah:** `grid-cols-[repeat(auto-fill,minmax(14rem,1fr))] gap-4`.

## 6. Ikon

Heroicons outline 24 px, `stroke-width` 1.5, ukuran bawaan `size-5`; di tombol `size-4`; di badge memakai varian **solid 16 px** (`size-3.5`).
Warna mengikuti `currentColor`. Ikon dekoratif `aria-hidden="true"`; tombol hanya-ikon wajib punya teks `sr-only` atau `aria-label`.
Komponen: `<x-icon name="academic-cap" [solid] />`. Nama ikon tak dikenal melempar galat saat development.

Pemetaan semantik yang konsisten: tugas `clipboard-document-list`, kuis `academic-cap`, pengumuman `megaphone`, nilai `check-badge`,
pengingat `clock`, notifikasi `bell`, hapus `trash`, sembunyikan/tampilkan `eye-slash`/`eye`, ubah `pencil-square`, tambah `plus`, kembali `arrow-left`.

## 7. Komponen

Semua komponen ada sebagai Blade component (`resources/views/components`). Resep kelas di bawah dapat dipakai langsung di framework lain.

### Tombol (`<x-button>`)

Dasar: `inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg font-medium transition disabled:cursor-not-allowed disabled:opacity-60`.

| Varian      | Kelas                                                              | Pakai untuk                        |
| ----------- | ------------------------------------------------------------------ | ---------------------------------- |
| `primary`   | `bg-primary text-primary-fg hover:opacity-90`                      | Satu aksi utama per area           |
| `secondary` | `border border-border bg-surface text-text hover:bg-surface-muted` | Aksi sekunder, batal               |
| `ghost`     | `text-text hover:bg-surface-muted`                                 | Aksi tersier, tombol ikon di baris |
| `danger`    | `bg-danger text-white hover:opacity-90` (gelap: teks hitam)        | Aksi merusak                       |

Ukuran: `sm` `min-h-9 px-3 text-sm`, `md` `min-h-11 px-4 text-base` (bawaan), `lg` `min-h-12 px-6`. Ikon di kiri teks. Tombol hanya-ikon tampil dengan varian `ghost` dan `size="sm"`.
Tombol yang sedang memproses diberi `disabled` selama permintaan berjalan.

### Kartu (`<x-card>`)

`rounded-card border border-border bg-surface`; isi `p-4 sm:p-5`; header opsional `flex items-center justify-between gap-3 border-b border-border px-4 py-3 sm:px-5` dengan judul `text-base font-semibold`
dan slot aksi di kanan. Kartu berisi daftar memakai `padding=false` dan `divide-y divide-border`.

### Kartu mata kuliah / banner

`overflow-hidden rounded-card border border-border bg-surface transition hover:border-primary`; gambar banner `h-24 w-full object-cover` (rasio sumber 3:1, mis. 1200×400);
isi `p-4` berisi judul `font-semibold truncate`, keterangan `text-xs text-text-muted`, lalu angka ringkas `grid grid-cols-2|3 gap-3 text-xs` (label `text-text-muted`, nilai `text-base font-bold`).
Bila tidak ada gambar, pakai placeholder berwarna dengan nama mata kuliah.

### Tile statistik

`rounded-card border border-border bg-surface p-4`: label `text-xs font-medium text-text-muted`, angka `mt-1 text-2xl font-bold leading-none sm:text-3xl`, keterangan opsional `text-xs font-semibold` (warna semantik).
Grid `grid-cols-2 gap-3 sm:grid-cols-3`; tile ganjil terakhir `col-span-2 sm:col-span-1`.

### Baris daftar

`flex min-h-14 items-center gap-3 px-4 py-2.5 hover:bg-surface-muted`: ikon `text-primary` di kiri, blok teks `min-w-0 flex-1` (judul `text-sm font-medium truncate`, keterangan `text-xs text-text-muted truncate`),
penanda di kanan (badge atau waktu `text-xs`). Waktu yang mendesak (< 24 jam) memakai `font-semibold text-warning`.
Item yang disembunyikan tampil `opacity-60` dengan penanda teks "Disembunyikan".

### Badge (`<x-badge>`)

`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold`; varian: `neutral` (`bg-surface-muted text-text-muted`), `primary`, `success`, `warning`, `danger`, `info` (`bg-{token}/15 text-{token}`).

**Status baku** (`<x-status-badge>`): Belum dikumpulkan = neutral + `circle`; Dikumpulkan/Menunggu = info + `arrow-up-tray`; Terlambat = warning + `clock`; Dinilai = success + `check-circle`; Perlu revisi = danger + `arrow-path`.
Kuis: Draf = warning, Akan dibuka = info, Dibuka = success, Ditutup = neutral. Presensi: Hadir success, Izin info, Sakit warning, Alpa danger. Warna status sama di seluruh aplikasi.

### Formulir

- Label di atas isian: `block text-sm font-medium text-text`, jarak `space-y-1.5`; keterangan `text-sm text-text-muted`; galat `text-sm text-danger` dengan `role="alert"` dan `aria-invalid="true"` pada isian.
- Isian `.input` (`min-h-11`, `rounded-lg`, tepi `border`); area teks memakai kelas yang sama. Kotak centang/radio `size-5 accent-primary`, dibungkus label `min-h-11`.
- Tombol kirim di kiri, tombol Batal `secondary` di sebelahnya (`flex flex-wrap gap-2`). Formulir panjang: kelompokkan per kartu.
- Tanggal memakai Flatpickr (opsi waktu, bisa dikosongkan); berkas memakai FilePond; teks kaya memakai Tiptap (+ KaTeX untuk rumus); pilihan banyak memakai Tom Select.

### Tabel responsif (`.table-responsive`)

Desktop: tabel dengan header `text-xs font-semibold uppercase tracking-wide text-text-muted`, sel `px-4 py-3`, garis antar baris. Di bawah `sm` (640 px) setiap baris menjadi kartu; sel diberi `data-label` agar label tampil di kiri nilai.
Tabel yang sangat lebar (buku nilai) memakai pembungkus `overflow-x-auto` dengan kolom pertama `sticky left-0`.

### Navigasi, tab, dan menu

- Item sidebar/bar bawah: aktif `bg-primary/15 text-primary` (sidebar) atau `text-primary` (bar bawah); tidak aktif `text-text-muted hover:bg-surface-muted`; ikon di atas label pada bar bawah.
- Tab: garis bawah 2 px; aktif `border-primary text-primary`, tidak aktif `text-text-muted`; navigasi panah kiri/kanan.
- Dropdown: panel `min-w-48 rounded-lg border border-border bg-surface p-1 shadow-sm`; item `min-h-10 rounded-md px-3 text-sm hover:bg-surface-muted`; aksi merusak `text-danger`.

### Modal

Overlay `bg-black/50`; panel `max-w-lg border border-border bg-surface p-5`, di HP menempel bawah dengan `rounded-t-2xl`, di desktop di tengah `rounded-card`. Tutup dengan Esc atau klik latar; fokus terkunci di dalam modal.

### Keadaan kosong, memuat, dan galat

- **Kosong:** ikon dalam lingkaran `bg-surface-muted`, judul `font-semibold`, deskripsi `text-sm text-text-muted max-w-sm`, aksi opsional. Selalu jelaskan langkah berikutnya.
- **Memuat:** `.skeleton` (`animate-pulse bg-surface-muted`); hindari spinner penuh halaman.
- **Halaman galat** (403, 404, 419, 429, 500, 503): ikon dalam lingkaran `bg-primary/15`, kode kecil, judul, penjelasan ramah, tombol "Ke beranda" dan "Kembali".

### Progress bar

Label kiri `text-xs font-medium text-text-muted`, persentase kanan `text-xs font-semibold tabular-nums` beserta hitungan `(2/3)`; jalur `h-2 rounded-full bg-surface-muted`, isi `bg-primary` (100% = `bg-success`);
`role="progressbar"` dengan `aria-valuenow/min/max` dan `aria-label`. Tanpa data: teks "Belum ada …", bukan 0%.

### Notifikasi singkat dan konfirmasi (SweetAlert2)

- **Toast:** pojok kanan atas, otomatis hilang ±3,5 dtk, ada tombol tutup, berhenti saat disorot; ikon status (success, error, warning, info). Dipicu event `toast` `{ message, type }`.
- **Konfirmasi:** semua aksi merusak lewat dialog (`wire:confirm` diubah otomatis). Tombol konfirmasi merah untuk penghapusan, aksen untuk lainnya; tombol "Batal" sekunder; urutan: Batal kiri, konfirmasi kanan.
- Gaya dialog mengikuti token (permukaan, teks, tepi, radius kartu, tombol `min-h-11`).

### Lonceng notifikasi

Tombol `size-11` dengan lencana bulat `bg-primary text-primary-fg text-[10px]` (99+ bila lebih); panel `w-[min(22rem,calc(100vw-1.5rem))]` berisi hingga 6 item (belum dibaca `bg-primary/5` + titik `bg-primary`) dan tautan "Lihat semua".

### Grafik (Chart.js)

Warna seri berurutan: `primary`, `success`, `warning`, `text-muted` (merah mirip bahaya, jadi seri keempat abu-abu). Teks sumbu `text-muted`, garis `border`; dirender ulang saat tema berganti.
Batang tunggal satu warna aksen, orientasi horizontal untuk nama panjang (label dipotong ~20 karakter), sumbu nilai 0–100, tanpa legenda bila satu seri. Tinggi `h-56`–`h-64`. Doughnut untuk sebaran nilai (A–E).

## 8. Pola halaman

- **Beranda (per peran):** kepala (tanggal, sapaan, peran) → tile ringkasan → dua daftar berdampingan (tenggat, nilai/perlu dinilai) → kartu mata kuliah → grafik.
- **Halaman mata kuliah:** banner → info + aksi → tombol menu (buku nilai, presensi, pengumuman) → bar kemajuan (mahasiswa) → daftar pertemuan yang bisa dilipat; tiap item punya kolom aksi lebar tetap (`w-20`) agar tombol sejajar.
- **Halaman masuk:** dua panel; kiri `brand` dengan lingkaran dekoratif `bg-white/10` dan petunjuk, kanan formulir `max-w-md`; di HP panel merek menjadi header dan petunjuk pindah ke bawah formulir.
- **Profil:** banner `brand` setinggi 6 rem + foto bulat `size-24` menumpuk (`-mt-12`, `border-4 border-surface`), data hanya-tampil, tombol "Ubah profil" memisahkan halaman ubah.
- **Kuis:** satu soal per layar di HP, navigasi nomor, penghitung waktu `text-base font-semibold tabular-nums` (≤ 60 dtk `bg-danger/15 text-danger`), tanda ragu-ragu, simpan otomatis dengan indikator status.

## 9. Gerak

- GSAP inti, dimuat lazy; hanya `opacity` dan `transform`; durasi ~0.35 dtk, `power2.out`, jeda antar item 0.05 dtk, maksimum 12 elemen.
- Konten tidak pernah tertahan: bila GSAP belum siap dalam 600 ms, elemen langsung tampil. Mati total bila `prefers-reduced-motion: reduce`.
- Animasi muncul hanya dijalankan sebelum pembaruan Livewire pertama (menghindari elemen tertinggal transparan).
- Transisi UI: `transition` standar Tailwind untuk hover/warna; tanpa animasi dekoratif yang berulang.

## 10. Aksesibilitas (wajib)

- Kontras teks ≥ 4.5:1 (3:1 untuk teks ≥ 24 px); jangan menandai status hanya dengan warna.
- Elemen interaktif memakai `<button>`/`<a>`/`<input>` asli; ikon-saja punya nama aksesibel.
- Fokus terlihat (`outline-2 outline-offset-2 outline-primary`), urutan tab logis, modal mengunci fokus, Esc menutup.
- Tautan lompat ke konten di awal halaman; satu `<h1>` per halaman; wilayah penting diberi `aria-label`/`aria-labelledby`.
- Teks bantu dinamis memakai `role="status"` atau `aria-live="polite"`; galat formulir `role="alert"`.
- Semua teks antarmuka melalui berkas terjemahan; Bahasa Indonesia, nada ramah dan lugas ("Kamu belum mengumpulkan", "Simpan perubahan").

## 11. PWA dan identitas

- `theme-color` `#c62828`, ikon aplikasi: topi wisuda putih (`academic-cap`) di atas `#c62828`; PNG 192/512 dan versi maskable.
- Nama aplikasi "Aksaraloka"; nama institusi tampil di header dan judul tab (`{halaman} · {institusi}`).
- Gambar privat (banner, foto profil) disajikan lewat rute yang memeriksa hak akses, bukan URL berkas langsung.

## 12. Cara memakai di aplikasi lain

1. Pasang Tailwind v4 dan font Plus Jakarta Sans; salin blok CSS pada bagian 3.
2. Pasang skrip tema anti-kedip di `<head>` dan pemilih tema (Terang/Gelap/Ikuti sistem) yang menyimpan pilihan di `localStorage`.
3. Buat komponen dasar (tombol, kartu, badge, input, daftar, kosong) dengan resep kelas pada bagian 7; jangan menambahkan hex baru.
4. Pakai Heroicons dan pemetaan ikon semantik; pakai SweetAlert2 bertema untuk konfirmasi dan toast.
5. Terapkan kerangka (sidebar + header + bar bawah HP) dan ritme jarak pada bagian 5.
6. Uji: kontras token (WCAG AA), tampilan 390 px, mode gelap, `prefers-reduced-motion`, dan navigasi keyboard.
