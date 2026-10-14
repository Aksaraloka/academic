<?php

namespace Database\Seeders;

use App\Enums\DefenseResult;
use App\Enums\GuidanceStatus;
use App\Enums\Role;
use App\Enums\ThesisStatus;
use App\Models\GraduationRegistration;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\Thesis;
use App\Models\User;
use App\Models\Yudisium;
use App\Services\YudisiumService;
use App\Support\GradeScale;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Tugas akhir angkatan 2022 di berbagai tahap (pengajuan, bimbingan, sidang, lulus) dan yudisium:
 * satu yudisium yang sudah ditetapkan untuk calon wisudawan, satu yudisium draf untuk lulusan berikutnya.
 */
class ThesisYudisiumSeeder extends Seeder
{
    /** @var array<int, string> */
    private const TITLES = [
        'Rancang Bangun Aplikasi Mobile untuk Layanan Kesehatan Masyarakat',
        'Analisis Sentimen Ulasan Pengguna Menggunakan Metode Naive Bayes',
        'Sistem Rekomendasi Mata Kuliah Berbasis Content-Based Filtering',
        'Implementasi IoT untuk Pemantauan Kualitas Udara Dalam Ruangan',
        'Pengaruh Transformasi Digital terhadap Kinerja Usaha Mikro Kecil',
        'Evaluasi Kepuasan Pengguna Sistem Informasi Akademik dengan Metode UTAUT',
        'Analisis Faktor yang Mempengaruhi Keputusan Pembelian Produk Lokal Secara Daring',
        'Perancangan Sistem Informasi Persediaan pada Toko Retail Menengah',
        'Optimasi Rute Pengiriman Menggunakan Algoritma Genetika',
        'Strategi Pemasaran Digital dalam Meningkatkan Loyalitas Pelanggan',
    ];

    public function seed(Tenant $tenant, User $actor): void
    {
        auth()->setUser($actor);

        try {
            $teachers = Teacher::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('id')->get();
            $scale = GradeScale::for($tenant);

            // Calon wisudawan yang sudah terdaftar wisuda: tugas akhir selesai, lalu yudisium ditetapkan.
            $registrations = GraduationRegistration::query()->withoutGlobalScopes()->with('student')->where('tenant_id', $tenant->id)->get();

            foreach ($registrations as $index => $registration) {
                $this->thesis($tenant, $registration->student, ThesisStatus::Completed, $teachers, $scale, $registration->thesis_title, $registration->defense_date->toDateString(), $index);
            }

            $done = Yudisium::create(['tenant_id' => $tenant->id, 'name' => 'Yudisium Periode Agustus 2026', 'held_on' => '2026-08-20', 'number' => 'SK/YUD/008/VIII/2026', 'status' => 'draft']);
            app(YudisiumService::class)->finalize($done, $actor, $registrations->pluck('student_id')->all(), null);

            Yudisium::create(['tenant_id' => $tenant->id, 'name' => 'Yudisium Periode Oktober 2026', 'held_on' => today()->addDays(15), 'status' => 'draft']);

            // Angkatan 2022 lainnya di berbagai tahap.
            $students = Student::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('batch_year', 2022)->where('status', 'aktif')->orderBy('id')->get();
            $stages = collect([
                ...array_fill(0, 12, ThesisStatus::Completed), ...array_fill(0, 4, ThesisStatus::DefenseScheduled), ...array_fill(0, 3, ThesisStatus::DefenseRequested),
                ...array_fill(0, 10, ThesisStatus::Supervised), ...array_fill(0, 5, ThesisStatus::Proposed),
            ]);

            $demoDone = false;

            foreach ($students as $index => $student) {
                $stage = $stages->get($index);

                if ($stage === null) {
                    continue;
                }

                $thesis = $this->thesis($tenant, $student, $stage, $teachers, $scale, self::TITLES[$index % count(self::TITLES)], null, $index);

                if (! $demoDone && $stage === ThesisStatus::Supervised) {
                    $user = User::create(['tenant_id' => $tenant->id, 'name' => $student->name, 'email' => 'skripsi@aksaraloka.test', 'role' => Role::Student, 'password' => 'password']);
                    $student->update(['user_id' => $user->id]);
                    $thesis->logs()->where('status', GuidanceStatus::Verified)->skip(5)->take(10)->get()->each->update(['status' => GuidanceStatus::Pending, 'verified_at' => null]);
                    $demoDone = true;
                }
            }
        } finally {
            auth()->logout();
        }
    }

    /**
     * @param  Collection<int, Teacher>  $teachers
     */
    private function thesis(Tenant $tenant, Student $student, ThesisStatus $status, Collection $teachers, GradeScale $scale, string $title, ?string $defenseDate, int $index): Thesis
    {
        $primary = $teachers[$index % 2];
        $secondary = $teachers[($index + 1) % 2];

        $thesis = Thesis::create([
            'tenant_id' => $tenant->id, 'student_id' => $student->id, 'title' => $title,
            'abstract' => 'Penelitian ini membahas permasalahan pada objek studi dan mengusulkan pendekatan penyelesaian yang dapat diuji secara terukur.',
            'status' => $status, 'approved_at' => $status === ThesisStatus::Proposed ? null : now()->subMonths(6), 'approved_by' => null,
        ]);

        if ($status === ThesisStatus::Proposed) {
            return $thesis;
        }

        $thesis->supervisors()->create(['teacher_id' => $primary->id, 'role' => 'primary']);
        $thesis->supervisors()->create(['teacher_id' => $secondary->id, 'role' => 'secondary']);

        $logCount = match ($status) {
            ThesisStatus::Supervised => random_int(2, 7),
            default => random_int(8, 11),
        };

        foreach (range(1, $logCount) as $number) {
            $verified = $status !== ThesisStatus::Supervised || $number <= $logCount - 1;

            $thesis->logs()->create([
                'teacher_id' => ($number % 3 === 0 ? $secondary : $primary)->id, 'held_on' => today()->subDays(($logCount - $number) * 9 + random_int(3, 8)),
                'topic' => ['Revisi latar belakang dan rumusan masalah', 'Pembahasan tinjauan pustaka', 'Perancangan metodologi', 'Hasil pengujian awal', 'Analisis hasil', 'Penyusunan kesimpulan', 'Revisi naskah lengkap', 'Persiapan presentasi sidang'][($number - 1) % 8],
                'summary' => 'Mahasiswa memaparkan kemajuan pekerjaan; pembimbing memberi masukan perbaikan struktur dan pembahasan.',
                'next_steps' => 'Memperbaiki bagian yang dikomentari sebelum pertemuan berikutnya.',
                'status' => $verified ? GuidanceStatus::Verified : GuidanceStatus::Pending, 'verified_at' => $verified ? now()->subDays(1) : null,
            ]);
        }

        if (in_array($status, [ThesisStatus::DefenseRequested, ThesisStatus::DefenseScheduled, ThesisStatus::Completed], true)) {
            $thesis->update(['defense_consent_at' => now()->subMonths(1)]);
        }

        if (in_array($status, [ThesisStatus::DefenseScheduled, ThesisStatus::Completed], true)) {
            $chair = $teachers->first(fn (Teacher $teacher): bool => $teacher->id !== $primary->id);
            $examiner = $teachers->first(fn (Teacher $teacher): bool => ! in_array($teacher->id, [$primary->id, $chair->id], true));
            $date = $status === ThesisStatus::Completed ? ($defenseDate ?? today()->subDays(random_int(10, 60))->toDateString()) : today()->addDays(random_int(3, 14))->toDateString();
            $score = round(random_int(7600, 9400) / 100, 2);

            $thesis->defenses()->create([
                'scheduled_on' => $date, 'starts_at' => '09:00', 'ends_at' => '10:30', 'chair_id' => $chair->id, 'examiner_id' => $examiner->id,
                'status' => $status === ThesisStatus::Completed ? 'done' : 'scheduled',
                'result' => $status === ThesisStatus::Completed ? DefenseResult::Passed : null, 'score' => $status === ThesisStatus::Completed ? $score : null,
            ]);

            if ($status === ThesisStatus::Completed) {
                $thesis->update(['completed_at' => $date, 'final_score' => $score, 'final_letter' => $scale->letterFor($score)]);
            }
        }

        return $thesis;
    }
}
