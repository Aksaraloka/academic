<?php

namespace Database\Seeders;

use App\Enums\ActivityCategory;
use App\Enums\ActivityLevel;
use App\Enums\ConversionSource;
use App\Enums\InternshipType;
use App\Enums\LetterType;
use App\Models\Internship;
use App\Models\Scholarship;
use App\Models\Student;
use App\Models\StudentActivity;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ActivityService;
use App\Services\CreditConversionService;
use App\Services\InternshipService;
use App\Services\LetterService;
use App\Services\ScholarshipService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Layanan mandiri mahasiswa: surat terbit dan menunggu, aktivitas kemahasiswaan, beasiswa, magang/MBKM, dan konversi SKS.
 * Dipanggil dari DatabaseSeeder setelah riwayat nilai dan tugas akhir tersedia.
 */
class SelfServiceSeeder extends Seeder
{
    /** @var array<int, array{0: ActivityCategory, 1: string, 2: string, 3: ?string, 4: ActivityLevel}> */
    private const ACTIVITIES = [
        [ActivityCategory::Organization, 'Pengurus Himpunan Mahasiswa', 'Himpunan Mahasiswa Program Studi', 'Sekretaris', ActivityLevel::Campus],
        [ActivityCategory::Organization, 'Anggota Unit Kegiatan Mahasiswa Robotika', 'UKM Robotika', 'Anggota divisi riset', ActivityLevel::Campus],
        [ActivityCategory::Committee, 'Panitia Orientasi Mahasiswa Baru', 'BEM Universitas', 'Koordinator acara', ActivityLevel::Campus],
        [ActivityCategory::Achievement, 'Lomba Karya Tulis Ilmiah Tingkat Provinsi', 'Dinas Pendidikan Provinsi', 'Juara 2', ActivityLevel::Regional],
        [ActivityCategory::Achievement, 'Kompetisi Inovasi Teknologi Nasional', 'Kemendikbudristek', 'Finalis', ActivityLevel::National],
        [ActivityCategory::Certification, 'Sertifikasi Kompetensi Analisis Data', 'Badan Nasional Sertifikasi Profesi', 'Analis data junior', ActivityLevel::National],
        [ActivityCategory::Certification, 'Pelatihan Bahasa Inggris Akademik (TOEFL ITP 520)', 'Pusat Bahasa Kampus', null, ActivityLevel::Campus],
        [ActivityCategory::Service, 'Mengajar Anak Putus Sekolah', 'Komunitas Rumah Belajar', 'Relawan pengajar', ActivityLevel::Regional],
    ];

    public function seed(Tenant $tenant, User $actor): void
    {
        auth()->setUser($actor);

        $students = Student::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('status', 'aktif')
            ->where('batch_year', 2022)->orderBy('id')->take(24)->get();
        $teachers = Teacher::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('id')->get();

        if ($students->count() < 10 || $teachers->isEmpty()) {
            auth()->logout();

            return;
        }

        try {
            $this->letters($students, $teachers, $actor);
            $this->activities($students, $actor);
            $this->scholarships($tenant, $students, $actor);
            $this->internships($students, $teachers, $actor);
        } finally {
            auth()->logout();
        }
    }

    /**
     * @param  Collection<int, Student>  $students
     * @param  Collection<int, Teacher>  $teachers
     */
    private function letters(Collection $students, Collection $teachers, User $actor): void
    {
        $letters = app(LetterService::class);

        foreach ($students->take(8) as $index => $student) {
            $letters->request($student, LetterType::ActiveStudent, ['Pengurusan beasiswa', 'Administrasi BPJS Kesehatan', 'Persyaratan magang'][$index % 3]);
        }

        foreach ($students->take(3) as $student) {
            $letters->request($student, LetterType::ProvisionalTranscript, 'Melamar program magang');
        }

        $letters->request($students[3], LetterType::Recommendation, 'Beasiswa pertukaran pelajar ke luar negeri', ['addressee' => 'Panitia Beasiswa LPDP', 'recommender_id' => $teachers->first()->id]);
        $letters->request($students[4], LetterType::Recommendation, 'Program magang bersertifikat', ['addressee' => 'PT Teknologi Nusantara']);
        $letters->request($students[5], LetterType::ResearchPermit, 'Analisis Sentimen Ulasan Pengguna Menggunakan Metode Naive Bayes', ['institution' => 'Dinas Komunikasi dan Informatika Kota', 'addressee' => 'Kepala Dinas', 'period_start' => today()->addDays(7)->toDateString(), 'period_end' => today()->addDays(60)->toDateString()]);

        $approved = $letters->request($students[6], LetterType::Recommendation, 'Beasiswa unggulan mahasiswa berprestasi', ['addressee' => 'Panitia Beasiswa Unggulan']);
        $letters->approve($approved, $actor, 'Disetujui.');

        $rejected = $letters->request($students[7], LetterType::ResearchPermit, 'Studi lapangan', ['institution' => 'Perusahaan X', 'addressee' => 'Direktur', 'period_start' => today()->toDateString(), 'period_end' => today()->addDays(30)->toDateString()]);
        $letters->reject($rejected, $actor, 'Judul penelitian belum disetujui pembimbing. Lampirkan persetujuan lalu ajukan ulang.');
    }

    /**
     * @param  Collection<int, Student>  $students
     */
    private function activities(Collection $students, User $actor): void
    {
        $service = app(ActivityService::class);

        foreach ($students->take(16) as $index => $student) {
            foreach ([0, 1, 2] as $offset) {
                [$category, $title, $organizer, $role, $level] = self::ACTIVITIES[($index + $offset * 3) % count(self::ACTIVITIES)];

                $activity = StudentActivity::create([
                    'tenant_id' => $student->tenant_id, 'student_id' => $student->id, 'category' => $category, 'level' => $level,
                    'title' => $title, 'organizer' => $organizer, 'role_or_result' => $role,
                    'started_on' => today()->subMonths(2 + $index + $offset * 4)->startOfMonth(), 'ended_on' => today()->subMonths($index + $offset * 4)->startOfMonth(),
                    'description' => 'Kegiatan diikuti selama masa perkuliahan.', 'status' => 'pending',
                ]);

                match (($index + $offset) % 4) {
                    0, 1 => $service->verify($activity, $actor, $service->suggestedPoints($activity), null),
                    2 => $service->reject($activity, $actor, 'Bukti kegiatan belum dilampirkan. Unggah sertifikat atau SK lalu ajukan ulang.'),
                    default => null,
                };
            }
        }
    }

    /**
     * @param  Collection<int, Student>  $students
     */
    private function scholarships(Tenant $tenant, Collection $students, User $actor): void
    {
        $service = app(ScholarshipService::class);
        $base = ['tenant_id' => $tenant->id, 'is_active' => true];

        $open = Scholarship::create($base + [
            'name' => 'Beasiswa Prestasi Akademik', 'provider' => 'Yayasan Aksaraloka', 'amount_per_term' => 3500000, 'quota' => 5, 'min_gpa' => 3.25, 'min_semester' => 3,
            'description' => 'Bantuan biaya pendidikan untuk mahasiswa dengan prestasi akademik terbaik di tiap program studi.',
            'requirements' => 'Tidak sedang menerima beasiswa lain. Melampirkan surat keterangan aktif kuliah (bisa diajukan di Permohonan surat).',
            'opens_on' => today()->subDays(10), 'closes_on' => today()->addDays(20),
        ]);
        $open2 = Scholarship::create($base + [
            'name' => 'Beasiswa Peduli Mahasiswa', 'provider' => 'Dana Sosial Kampus', 'amount_per_term' => 2000000, 'quota' => 10, 'min_semester' => 2,
            'description' => 'Keringanan biaya kuliah bagi mahasiswa yang menghadapi kesulitan ekonomi.',
            'opens_on' => today()->subDays(3), 'closes_on' => today()->addDays(30),
        ]);
        Scholarship::create($base + [
            'name' => 'Beasiswa Pertukaran Pelajar', 'provider' => 'Kemitraan Universitas Luar Negeri', 'quota' => 2, 'min_gpa' => 3.5, 'min_semester' => 4,
            'description' => 'Program satu semester di universitas mitra. Pendaftaran periode sebelumnya sudah ditutup.',
            'opens_on' => today()->subDays(60), 'closes_on' => today()->subDays(15),
        ]);

        foreach ($students->take(6) as $index => $student) {
            try {
                $application = $service->apply($student, $index % 2 === 0 ? $open : $open2, 'Saya membutuhkan dukungan biaya pendidikan agar bisa fokus menyelesaikan kuliah tepat waktu dan terus berprestasi.');
            } catch (ValidationException) {
                continue;
            }

            if ($index === 0) {
                $service->accept($application, $actor, 'Memenuhi seluruh persyaratan.');
            } elseif ($index === 1) {
                $service->reject($application, $actor, 'Kuota prioritas diberikan kepada pendaftar dengan IPK lebih tinggi.');
            }
        }
    }

    /**
     * @param  Collection<int, Student>  $students
     * @param  Collection<int, Teacher>  $teachers
     */
    private function internships(Collection $students, Collection $teachers, User $actor): void
    {
        $internships = app(InternshipService::class);
        $conversions = app(CreditConversionService::class);

        $rows = [
            [InternshipType::Internship, 'PT Teknologi Nusantara', 'Software Engineer Intern', 'Bandung', -150, -45, 'completed', 'A'],
            [InternshipType::IndependentStudy, 'Bangkit Academy', 'Peserta studi independen', 'Daring', -140, -40, 'completed', 'B'],
            [InternshipType::Internship, 'Bank Mitra Sejahtera', 'Analis data magang', 'Jakarta', -30, 90, 'approved', null],
            [InternshipType::Research, 'Pusat Riset Teknologi Nasional', 'Asisten peneliti', 'Bogor', -20, 100, 'approved', null],
            [InternshipType::VillageProject, 'Pemerintah Desa Sukamaju', 'Anggota tim proyek desa', 'Sumedang', 40, 130, 'submitted', null],
            [InternshipType::Exchange, 'Universitas Mitra Malaysia', 'Mahasiswa pertukaran', 'Kuala Lumpur', 60, 180, 'submitted', null],
            [InternshipType::Entrepreneurship, 'Inkubator Bisnis Kampus', 'Founder startup', 'Kampus', 30, 120, 'rejected', null],
        ];

        foreach ($rows as $index => [$type, $partner, $position, $location, $startOffset, $endOffset, $state, $letter]) {
            $student = $students[$index + 8] ?? $students[$index];

            $internship = Internship::create([
                'tenant_id' => $student->tenant_id, 'student_id' => $student->id, 'type' => $type, 'partner_name' => $partner, 'position' => $position,
                'location' => $location, 'field_supervisor' => 'Pembimbing Lapangan '.($index + 1), 'starts_on' => today()->addDays($startOffset), 'ends_on' => today()->addDays($endOffset),
                'description' => 'Kegiatan penuh waktu sesuai bidang studi dengan laporan berkala kepada dosen pembimbing.', 'proposed_credits' => 12, 'status' => 'submitted',
            ]);

            match ($state) {
                'completed' => [$internships->approve($internship, $actor, $teachers[$index % $teachers->count()]->id, 'Disetujui.'), $internships->complete($internship->refresh(), $actor, $letter, 'Kinerja sangat baik dan laporan akhir lengkap.')],
                'approved' => $internships->approve($internship, $actor, $teachers[$index % $teachers->count()]->id, 'Disetujui. Laporkan perkembangan tiap bulan.'),
                'rejected' => $internships->reject($internship, $actor, 'Rencana kegiatan belum memuat luaran yang jelas. Lengkapi lalu ajukan ulang.'),
                default => null,
            };

            if ($state === 'completed') {
                $subjects = $conversions->availableSubjects($student)->take(2);

                if ($subjects->isNotEmpty()) {
                    $conversion = $conversions->propose($student, ConversionSource::Internship, $internship->id, 'Magang penuh waktu setara kerja praktik dan mata kuliah pilihan.', $subjects->pluck('id')->all());

                    if ($index === 0) {
                        $conversions->approve($conversion->refresh(), $actor, $conversion->items->mapWithKeys(fn ($item): array => [$item->id => 'A'])->all(), 'Capaian magang sesuai capaian pembelajaran mata kuliah.');
                    }
                }
            }
        }
    }
}
