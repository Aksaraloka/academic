<?php

namespace Database\Seeders\Data;

/**
 * Kurikulum contoh per program studi: semester 1–8 (lima mata kuliah per semester) dan semester 9 (skripsi).
 */
final class Curriculum
{
    /**
     * @return array<string, array<int, array<int, array{0: string, 1: int}>>> kode prodi => semester => [[nama, SKS], ...]
     */
    public static function all(): array
    {
        return [
            'TI' => [
                1 => [['Pemrograman Dasar', 3], ['Matematika Teknik I', 3], ['Logika Informatika', 3], ['Sistem Digital', 3], ['Pendidikan Pancasila', 2]],
                2 => [['Struktur Data', 4], ['Matematika Teknik II', 3], ['Organisasi dan Arsitektur Komputer', 3], ['Pemrograman Berorientasi Objek', 3], ['Bahasa Inggris Teknik', 2]],
                3 => [['Analisis Algoritma', 3], ['Sistem Basis Data', 3], ['Jaringan Komputer', 3], ['Aljabar Linear', 3], ['Statistika dan Probabilitas', 3]],
                4 => [['Sistem Operasi', 3], ['Rekayasa Perangkat Lunak', 3], ['Pemrograman Web', 3], ['Interaksi Manusia dan Komputer', 3], ['Komunikasi Data', 3]],
                5 => [['Kecerdasan Buatan', 3], ['Pemrograman Mobile', 3], ['Keamanan Informasi', 3], ['Pemodelan dan Simulasi', 3], ['Metodologi Penelitian', 2]],
                6 => [['Pembelajaran Mesin', 3], ['Komputasi Awan', 3], ['Data Mining', 3], ['Manajemen Proyek TI', 3], ['Etika Profesi', 2]],
                7 => [['Kerja Praktik', 3], ['Rekayasa Kebutuhan', 3], ['Pengolahan Citra Digital', 3], ['Internet of Things', 3], ['Kewirausahaan', 2]],
                8 => [['Seminar Proposal', 2], ['Sistem Terdistribusi', 3], ['Computer Vision', 3], ['Pilihan Topik Khusus', 3], ['Kuliah Kerja Nyata', 3]],
                9 => [['Skripsi', 6]],
            ],
            'SI' => [
                1 => [['Pengantar Sistem Informasi', 3], ['Algoritma dan Logika', 3], ['Matematika Bisnis', 3], ['Pengantar Akuntansi', 3], ['Pendidikan Pancasila', 2]],
                2 => [['Basis Data Dasar', 3], ['Pemrograman Visual', 3], ['Statistika Bisnis', 3], ['Analisis Proses Bisnis', 3], ['Bahasa Inggris Bisnis', 2]],
                3 => [['Analisis dan Perancangan Sistem', 3], ['Basis Data Lanjut', 3], ['Jaringan dan Infrastruktur', 3], ['Sistem Informasi Manajemen', 3], ['Ekonomi Digital', 2]],
                4 => [['Pemrograman Web Lanjut', 3], ['Enterprise Resource Planning', 3], ['Kecerdasan Bisnis', 3], ['Manajemen Rantai Pasok Digital', 3], ['Audit Sistem Informasi', 3]],
                5 => [['Manajemen Proyek SI', 3], ['Sistem Pendukung Keputusan', 3], ['Tata Kelola TI', 3], ['E-Commerce', 3], ['Metodologi Penelitian', 2]],
                6 => [['Data Warehouse', 3], ['Keamanan Sistem Informasi', 3], ['Customer Relationship Management', 3], ['Analitik Data Bisnis', 3], ['Etika Profesi', 2]],
                7 => [['Kerja Praktik', 3], ['Arsitektur Enterprise', 3], ['Manajemen Layanan TI', 3], ['Sistem Informasi Geografis', 3], ['Kewirausahaan', 2]],
                8 => [['Seminar Proposal', 2], ['Inovasi Bisnis Digital', 3], ['Manajemen Risiko TI', 3], ['Pilihan Topik Khusus', 3], ['Kuliah Kerja Nyata', 3]],
                9 => [['Skripsi', 6]],
            ],
            'MJ' => [
                1 => [['Pengantar Manajemen', 3], ['Pengantar Ekonomi Mikro', 3], ['Matematika Ekonomi', 3], ['Akuntansi Dasar', 3], ['Pendidikan Pancasila', 2]],
                2 => [['Ekonomi Makro', 3], ['Statistika Ekonomi', 3], ['Perilaku Organisasi', 3], ['Manajemen Pemasaran', 3], ['Bahasa Inggris Bisnis', 2]],
                3 => [['Manajemen Keuangan', 3], ['Manajemen Sumber Daya Manusia', 3], ['Hukum Bisnis', 3], ['Akuntansi Biaya', 3], ['Manajemen Operasi', 3]],
                4 => [['Manajemen Strategik', 3], ['Riset Pemasaran', 3], ['Perpajakan', 3], ['Pasar Modal', 3], ['Sistem Informasi Manajemen', 3]],
                5 => [['Manajemen Investasi', 3], ['Perilaku Konsumen', 3], ['Analisis Laporan Keuangan', 3], ['Manajemen Risiko', 3], ['Metodologi Penelitian', 2]],
                6 => [['Manajemen Perbankan', 3], ['Studi Kelayakan Bisnis', 3], ['Manajemen Rantai Pasok', 3], ['Kepemimpinan', 3], ['Etika Bisnis', 2]],
                7 => [['Magang Kerja', 3], ['Bisnis Internasional', 3], ['Manajemen Perubahan', 3], ['Pemasaran Digital', 3], ['Kewirausahaan', 2]],
                8 => [['Seminar Proposal', 2], ['Manajemen Kinerja', 3], ['Pilihan Topik Khusus', 3], ['Manajemen Proyek', 3], ['Kuliah Kerja Nyata', 3]],
                9 => [['Skripsi', 6]],
            ],
        ];
    }
}
