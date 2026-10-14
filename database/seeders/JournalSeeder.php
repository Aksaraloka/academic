<?php

namespace Database\Seeders;

use App\Models\AttendanceSession;
use App\Models\Tenant;
use App\Services\JournalService;
use Illuminate\Database\Seeder;

/**
 * Jurnal perkuliahan (BAP) untuk sesi presensi yang sudah ada; sesi terbaru sebagian dibiarkan kosong
 * agar tampilan kelengkapan jurnal terlihat. Dipanggil dari DatabaseSeeder setelah riwayat presensi dibuat.
 */
class JournalSeeder extends Seeder
{
    /** @var array<int, array{0: string, 1: string}> */
    private const TOPICS = [
        ['Pengantar dan kontrak perkuliahan', 'Silabus, aturan penilaian, dan referensi utama mata kuliah.'],
        ['Konsep dasar', 'Definisi, istilah penting, dan contoh penerapan sederhana.'],
        ['Latihan terbimbing', 'Soal latihan dikerjakan bersama dan dibahas.'],
        ['Pendalaman materi', 'Studi kasus dan diskusi kelompok.'],
        ['Tugas kelompok', 'Pembagian kelompok dan pembahasan kerangka tugas.'],
        ['Presentasi tugas', 'Presentasi hasil kerja dan umpan balik dosen.'],
        ['Review sebelum ujian', 'Rangkuman materi dan pembahasan soal tahun lalu.'],
    ];

    public function seed(Tenant $tenant): void
    {
        $journals = app(JournalService::class);

        $sessions = AttendanceSession::query()->withoutGlobalScopes()
            ->with('schedule.assignment.teacher')
            ->where('tenant_id', $tenant->id)->whereNotNull('schedule_id')
            ->orderBy('date')->orderBy('id')->get()
            ->groupBy(fn (AttendanceSession $session): int => $session->schedule->teaching_assignment_id);

        foreach ($sessions as $rows) {
            foreach ($rows->values() as $index => $session) {
                $isLatest = $index >= $rows->count() - 2;

                if ($isLatest && random_int(1, 100) <= 45) {
                    continue;
                }

                [$topic, $material] = self::TOPICS[$index % count(self::TOPICS)];

                $journals->save($session, $session->schedule->assignment->teacher, [
                    'topic' => $topic.' ('.$session->schedule->assignment->subject->name.')', 'material' => $material,
                    'notes' => $index % 3 === 0 ? 'Tugas minggu depan diumumkan di kelas.' : null,
                ]);
            }
        }
    }
}
