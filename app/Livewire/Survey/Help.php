<?php

namespace App\Livewire\Survey;

use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Bantuan survey')]
class Help extends Component
{
    public function render()
    {
        return view('livewire.survey.help', [
            'faqs' => [
                ['Untuk apa kuesioner ini?', 'Hasilnya dipakai pimpinan dan unit terkait untuk memperbaiki layanan akademik, fasilitas, dan mutu pembelajaran. Jawabanmu dibaca dalam bentuk rekap.'],
                ['Siapa yang harus mengisi?', 'Setiap kuesioner punya sasaran responden, misalnya mahasiswa, dosen, atau orang tua. Kamu hanya melihat kuesioner yang ditujukan untuk perananmu.'],
                ['Apakah jawabanku anonim?', 'Kuesioner bertanda "Anonim" tidak menyimpan namamu bersama jawaban. Sistem hanya mencatat bahwa kamu sudah mengisi supaya tidak terisi dua kali. Pada kuesioner tidak anonim, jawaban tercatat atas namamu dan hanya bisa dilihat admin dan pimpinan.'],
                ['Bisakah jawaban diubah setelah dikirim?', 'Tidak. Jawaban dikunci setelah dikirim agar hasil tetap adil. Periksa dulu sebelum menekan tombol kirim.'],
                ['Kapan batas pengisian?', 'Tertera di setiap kuesioner. Setelah tanggal tutup, kuesioner tidak bisa diisi lagi. Kuesioner yang mendekati batas ditandai di Beranda survey.'],
                ['Pertanyaan bertanda bintang artinya apa?', 'Tanda * berarti pertanyaan wajib dijawab. Kuesioner baru terkirim bila semua pertanyaan wajib sudah terisi.'],
                ['Kuesioner tidak muncul atau tidak bisa dikirim, bagaimana?', 'Pastikan kuesioner sedang dibuka dan memang ditujukan untukmu. Bila masih bermasalah, sampaikan lewat menu Masukan agar kami bantu periksa.'],
            ],
        ]);
    }
}
