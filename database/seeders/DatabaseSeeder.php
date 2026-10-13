<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Faculty;
use App\Models\FeedbackReply;
use App\Models\FeedbackTicket;
use App\Models\GraduationPeriod;
use App\Models\GraduationRegistration;
use App\Models\Guardian;
use App\Models\News;
use App\Models\Program;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\Subject;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyResponse;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Tenant;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    /**
     * Poster contoh berformat SVG lanskap 16:9 bertema merah Aksaraloka.
     */
    private function posterSvg(string $title, string $badge, int $variant = 0): string
    {
        $lines = array_map(fn (string $line): string => htmlspecialchars($line, ENT_XML1), explode("\n", wordwrap($title, 26, "\n", true)));
        $text = '';

        foreach ($lines as $index => $line) {
            $y = 330 + $index * 78;
            $text .= "<text x=\"80\" y=\"{$y}\" font-family=\"Arial, sans-serif\" font-size=\"64\" font-weight=\"700\" fill=\"#ffffff\">{$line}</text>";
        }

        $badge = htmlspecialchars($badge, ENT_XML1);
        [$from, $to] = [['#c62828', '#7f1d1d'], ['#9f1f1f', '#450a0a'], ['#b91c1c', '#7c2d12']][$variant % 3];
        $badgeWidth = 60 + strlen($badge) * 20;

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1280 720" width="1280" height="720">
  <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{$from}"/><stop offset="1" stop-color="{$to}"/></linearGradient></defs>
  <rect width="1280" height="720" fill="url(#g)"/>
  <circle cx="1100" cy="130" r="260" fill="#ffffff" fill-opacity="0.10"/>
  <circle cx="1220" cy="640" r="170" fill="#ffffff" fill-opacity="0.08"/>
  <circle cx="60" cy="700" r="120" fill="#ffffff" fill-opacity="0.06"/>
  <rect x="80" y="110" width="{$badgeWidth}" height="58" rx="29" fill="#ffffff"/>
  <text x="110" y="149" font-family="Arial, sans-serif" font-size="26" font-weight="700" fill="#7f1d1d">{$badge}</text>
  {$text}
  <text x="80" y="650" font-family="Arial, sans-serif" font-size="30" fill="#ffffff" fill-opacity="0.9">Aksaraloka Academic</text>
</svg>
SVG;
    }

    /**
     * Alamat KTP dan domisili contoh. Mahasiswa perantau punya KTP di luar Bandung dan tinggal di kos.
     *
     * @return array<string, mixed>
     */
    private function residence(bool $migrant): array
    {
        if (! $migrant) {
            return [
                'city' => 'Bandung', 'province' => 'Jawa Barat', 'postal_code' => '40'.fake()->numerify('###'),
                'is_domicile_same' => true,
            ];
        }

        [$city, $province, $postal] = [
            ['Medan', 'Sumatera Utara', '20111'], ['Padang', 'Sumatera Barat', '25111'], ['Makassar', 'Sulawesi Selatan', '90111'],
            ['Pontianak', 'Kalimantan Barat', '78111'], ['Denpasar', 'Bali', '80111'],
        ][array_rand([0, 1, 2, 3, 4])];

        return [
            'city' => $city, 'province' => $province, 'postal_code' => $postal,
            'is_domicile_same' => false, 'residence_type' => 'Kos/kontrakan',
            'domicile_address' => 'Jl. '.fake('id_ID')->streetName().' No. '.random_int(1, 90).' (kos), Dago',
            'domicile_city' => 'Bandung', 'domicile_province' => 'Jawa Barat', 'domicile_postal_code' => '40135',
        ];
    }

    /**
     * Data contoh universitas (kata sandi semua akun: "password").
     */
    public function run(): void
    {
        $tenant = Tenant::create([
            'name' => 'Universitas Aksara Nusantara',
            'subdomain' => 'aksara-nusantara',
            'level' => 'campus',
            'settings' => ['attendance_mode' => 'per_hour', 'min_attendance_percent' => 75, 'absent_threshold' => 3],
        ]);

        $account = fn (string $name, string $email, Role $role): User => User::create([
            'tenant_id' => $tenant->id, 'name' => $name, 'email' => $email, 'role' => $role, 'password' => 'password',
        ]);

        $account('Admin BAAK', 'admin@aksaraloka.test', Role::Admin);
        $account('Prof. Dr. Maya Kaprodi, M.T.', 'kaprodi@aksaraloka.test', Role::Principal);
        $account('Petugas Presensi', 'petugas@aksaraloka.test', Role::Duty);

        $teachers = collect([
            ['Budi Santoso, M.Kom.', 'dosen@aksaraloka.test', Role::Teacher],
            ['Sari Wulandari, M.T.', 'dosen2@aksaraloka.test', Role::Teacher],
            ['Dr. Rina Kurniasih', 'akademik@aksaraloka.test', Role::Curriculum],
        ])->map(function (array $row, int $index) use ($account, $tenant): Teacher {
            $user = $account($row[0], $row[1], $row[2]);

            return Teacher::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'name' => $row[0], 'nip' => '0412068'.$index.'01', 'employment_status' => 'Dosen tetap']);
        });

        $engineering = Faculty::create(['tenant_id' => $tenant->id, 'name' => 'Fakultas Teknik']);
        $economics = Faculty::create(['tenant_id' => $tenant->id, 'name' => 'Fakultas Ekonomi dan Bisnis']);
        $informatics = Program::create(['tenant_id' => $tenant->id, 'faculty_id' => $engineering->id, 'name' => 'Teknik Informatika', 'code' => 'TI', 'degree' => 'S1']);
        Program::create(['tenant_id' => $tenant->id, 'faculty_id' => $engineering->id, 'name' => 'Sistem Informasi', 'code' => 'SI', 'degree' => 'S1']);
        Program::create(['tenant_id' => $tenant->id, 'faculty_id' => $economics->id, 'name' => 'Manajemen', 'code' => 'MJ', 'degree' => 'S1']);

        $term = AcademicTerm::create([
            'tenant_id' => $tenant->id, 'name' => '2026/2027', 'semester' => 'Ganjil',
            'starts_at' => '2026-09-07', 'ends_at' => '2027-01-15', 'is_active' => true,
        ]);

        $groups = collect(['TI-2024-A' => $teachers[0], 'TI-2024-B' => $teachers[1]])->map(fn (Teacher $advisor, string $name) => StudyGroup::create([
            'tenant_id' => $tenant->id, 'academic_term_id' => $term->id, 'program_id' => $informatics->id,
            'name' => $name, 'homeroom_teacher_id' => $advisor->id,
        ]));

        $subjects = collect([
            ['Algoritma dan Pemrograman', 'IF101', 3],
            ['Basis Data', 'IF201', 3],
            ['Kalkulus', 'MK101', 2],
        ])->map(fn (array $row) => Subject::create([
            'tenant_id' => $tenant->id, 'name' => $row[0], 'code' => $row[1], 'credits' => $row[2], 'semester' => 3, 'program_id' => $informatics->id,
        ]));

        $rooms = collect(['Ruang 3.01', 'Lab Komputer 1'])->map(fn (string $name) => Room::create(['tenant_id' => $tenant->id, 'name' => $name]));

        $periods = [[1, '07:30', '09:10'], [2, '09:20', '11:00'], [3, '12:30', '14:10'], [4, '14:20', '16:00']];
        $slots = collect();

        foreach ([1, 2, 3, 4, 5, 6] as $day) {
            foreach ($periods as [$number, $start, $end]) {
                $slots->push(TimeSlot::create(['tenant_id' => $tenant->id, 'day' => $day, 'period_number' => $number, 'starts_at' => $start, 'ends_at' => $end]));
            }
        }

        $students = collect();

        foreach ($groups->values() as $groupIndex => $group) {
            for ($i = 1; $i <= 15; $i++) {
                $student = Student::create([
                    'tenant_id' => $tenant->id,
                    'program_id' => $informatics->id,
                    'name' => fake('id_ID')->name(),
                    'nim' => (string) (2410000 + $groupIndex * 100 + $i),
                    'batch_year' => 2024,
                    'birth_place' => fake('id_ID')->city(),
                    'birth_date' => fake()->dateTimeBetween('-22 years', '-18 years')->format('Y-m-d'),
                    'gender' => $i % 2 === 0 ? 'P' : 'L',
                    'religion' => ['Islam', 'Kristen Protestan', 'Katolik', 'Hindu', 'Buddha'][array_rand([0, 1, 2, 3, 4])],
                    'nik' => '3273'.fake()->numerify('############'),
                    'address' => fake('id_ID')->streetAddress(),
                    ...$this->residence($i % 4 === 1),
                    'phone' => '08'.fake()->numerify('##########'),
                    'previous_school' => 'SMA Negeri '.random_int(1, 20).' Bandung',
                    'admission_path' => ['SNBP', 'SNBT', 'Mandiri'][array_rand([0, 1, 2])],
                    'status' => 'aktif',
                    'entry_date' => '2024-09-02',
                ]);
                $group->members()->create(['student_id' => $student->id, 'joined_at' => '2026-09-07']);
                $students->push($student);
            }
        }

        $studentUser = $account($students->first()->name, 'mahasiswa@aksaraloka.test', Role::Student);
        $students->first()->update(['user_id' => $studentUser->id]);

        $guardianUser = $account('Orang Tua Contoh', 'ortu@aksaraloka.test', Role::Guardian);
        $guardian = Guardian::create([
            'tenant_id' => $tenant->id, 'user_id' => $guardianUser->id, 'name' => $guardianUser->name, 'relation' => 'Ibu',
            'occupation' => 'Guru SD', 'education' => 'S1', 'phone' => '081234567890', 'address' => 'Jl. Merdeka No. 12, Bandung',
        ]);
        $guardian->students()->attach($students->first()->id);

        $father = Guardian::create([
            'tenant_id' => $tenant->id, 'name' => 'Bambang Hartono', 'relation' => 'Ayah',
            'occupation' => 'Wiraswasta', 'education' => 'SMA/SMK', 'phone' => '081298765432', 'address' => 'Jl. Merdeka No. 12, Bandung',
        ]);
        $father->students()->attach($students->first()->id);

        // Jadwal: tiap kelas punya tiga mata kuliah, masing-masing dua kali seminggu, tanpa bentrok dosen.
        $schedules = collect();

        foreach ($groups->values() as $groupIndex => $group) {
            foreach ($subjects as $subjectIndex => $subject) {
                $assignment = TeachingAssignment::create([
                    'tenant_id' => $tenant->id, 'academic_term_id' => $term->id,
                    'teacher_id' => $teachers[$subjectIndex]->id,
                    'subject_id' => $subject->id, 'study_group_id' => $group->id, 'hours_per_week' => 2,
                ]);

                // Mata kuliah ketiga berlangsung Senin dan Sabtu; lainnya Senin dan Rabu.
                foreach ([1, $subjectIndex === 2 ? 6 : 3] as $day) {
                    $period = (($subjectIndex + $groupIndex) % 3) + 1;
                    $slot = $slots->first(fn (TimeSlot $candidate) => $candidate->day === $day && $candidate->period_number === $period);
                    $schedules->put("{$assignment->id}-{$day}", Schedule::create([
                        'tenant_id' => $tenant->id, 'teaching_assignment_id' => $assignment->id,
                        'time_slot_id' => $slot->id, 'room_id' => $rooms[$groupIndex]->id,
                    ]));
                }
            }
        }

        // Periode wisuda yang sedang dibuka, dengan calon wisudawan angkatan 2022.
        $period = GraduationPeriod::create([
            'tenant_id' => $tenant->id, 'name' => 'Wisuda Periode II Tahun 2026',
            'registration_opens_on' => today()->subDays(14), 'registration_closes_on' => today()->addDays(20),
            'ceremony_on' => today()->addDays(60), 'venue' => 'Auditorium Utama', 'quota' => 100, 'is_published' => true,
        ]);

        $candidates = collect([
            ['Anindya Putri Lestari', 'approved', 3.72, 146],
            ['Rizky Fadillah', 'approved', 3.41, 148],
            ['Dewi Kartika Sari', 'approved', 3.88, 150],
            ['Muhammad Fajar Nugroho', 'submitted', 3.15, 145],
            ['Siti Nurhaliza', 'submitted', 3.56, 147],
            ['Yoga Pratama', 'rejected', 3.02, 146],
        ])->map(function (array $row, int $index) use ($tenant, $informatics, $period, $teachers): void {
            $student = Student::create([
                'tenant_id' => $tenant->id, 'program_id' => $informatics->id, 'name' => $row[0],
                'nim' => (string) (2210000 + $index + 1), 'batch_year' => 2022, 'gender' => $index % 2 === 0 ? 'P' : 'L',
                'status' => 'aktif', 'entry_date' => '2022-09-05',
            ]);

            GraduationRegistration::create([
                'tenant_id' => $tenant->id, 'graduation_period_id' => $period->id, 'student_id' => $student->id,
                'thesis_title' => 'Rancang Bangun Sistem Informasi '.['Akademik', 'Perpustakaan', 'Klinik', 'Koperasi', 'Pertanian', 'Parkir'][$index].' Berbasis Web',
                'gpa' => $row[2], 'credits_earned' => $row[3], 'defense_date' => today()->subDays(10 + $index),
                'gown_size' => ['S', 'M', 'L', 'M', 'S', 'XL'][$index],
                'status' => match ($row[1]) {
                    'approved' => 'disetujui', 'rejected' => 'ditolak', default => 'diajukan'
                },
                'rejection_reason' => $row[1] === 'rejected' ? 'Transkrip belum dilegalisir.' : null,
                'verified_by' => $row[1] === 'submitted' ? null : $teachers[2]->user_id,
                'verified_at' => $row[1] === 'submitted' ? null : now(),
            ]);
        });

        $adminUser = User::where('email', 'admin@aksaraloka.test')->first();

        collect([
            ['program', 'Program Studi Baru: Sains Data dan Kecerdasan Buatan', 'Penerimaan mahasiswa baru program studi S1 Sains Data dibuka mulai semester genap dengan kurikulum berbasis proyek industri.', 'PROGRAM BARU', 1],
            ['beasiswa', 'Beasiswa Prestasi Akademik 2026/2027 Dibuka', 'Potongan UKT hingga 100% bagi mahasiswa ber-IPK minimal 3,50. Pendaftaran ditutup akhir bulan ini.', 'BEASISWA', 2],
            ['wisuda', 'Pendaftaran Wisuda Periode II Dibuka', 'Pendaftaran dibuka hingga '.today()->addDays(20)->translatedFormat('j F Y').'. Lengkapi berkas lewat menu Wisuda.', 'WISUDA', 3],
            ['akademik', 'Jadwal Pengisian KRS Semester Genap', 'Pengisian KRS online dibuka bagi seluruh mahasiswa aktif. Konsultasikan rencana studi dengan dosen wali.', 'AKADEMIK', 4],
            ['kegiatan', 'Seminar Nasional Teknologi dan Inovasi', 'Seminar terbuka untuk umum menghadirkan pembicara dari industri dan akademisi, gratis bagi mahasiswa.', 'KEGIATAN', 6],
            ['prestasi', 'Tim Mahasiswa Juara 1 Kompetisi Pemrograman Nasional', 'Selamat kepada tim Teknik Informatika atas prestasi di tingkat nasional.', 'PRESTASI', 8],
            ['beasiswa', 'Beasiswa Mitra Industri untuk Mahasiswa Semester 5', 'Dukungan biaya studi dan peluang magang bagi mahasiswa terpilih dari program studi rumpun teknik.', 'BEASISWA', 10],
            ['pengumuman', 'Libur Akademik dan Layanan BAAK', 'Layanan BAAK tutup pada hari libur nasional dan dibuka kembali sesuai jam kerja.', 'PENGUMUMAN', 12],
            ['program', 'Program Pertukaran Mahasiswa Semester Depan', 'Kesempatan satu semester di universitas mitra dalam dan luar negeri. Cek persyaratan di BAAK.', 'PERTUKARAN', 14],
            ['kegiatan', 'Gladi Bersih Wisuda', 'Gladi bersih satu minggu sebelum hari-H di Auditorium Utama.', 'GLADI BERSIH', 0],
        ])->each(function (array $row, int $index) use ($tenant, $period, $adminUser): void {
            $path = "news-posters/seed-{$tenant->id}-{$index}.svg";
            Storage::disk('local')->put($path, $this->posterSvg($row[1], $row[3], $index));
            $draft = $row[4] === 0;

            News::create([
                'tenant_id' => $tenant->id, 'created_by' => $adminUser?->id, 'category' => $row[0],
                'graduation_period_id' => $row[0] === 'wisuda' ? $period->id : null,
                'title' => $row[1], 'summary' => $row[2], 'body' => $row[2]."\n\nInformasi lebih lanjut dapat ditanyakan ke BAAK pada jam kerja.",
                'poster_path' => $path, 'is_featured' => $index < 3, 'is_published' => ! $draft, 'published_at' => $draft ? null : now()->subDays($row[4]),
            ]);
        });

        // Survey contoh: kepuasan layanan (anonim, sudah ada responden), evaluasi dosen, survey dosen, dan satu draf.
        $extraStudents = collect(range(1, 12))->map(fn (int $n): User => User::create([
            'tenant_id' => $tenant->id, 'name' => "Mahasiswa Contoh {$n}", 'email' => "mhs{$n}@aksaraloka.test", 'role' => Role::Student, 'password' => 'password',
        ]));

        $surveyDefinitions = [
            [
                'title' => 'Evaluasi Layanan Akademik Semester Ganjil 2026/2027', 'category' => 'kepuasan', 'audience' => ['student'],
                'opens_on' => today()->subDays(3), 'closes_on' => today()->addDays(14), 'anonymous' => true, 'published' => true, 'responses' => 9,
                'description' => 'Penilaianmu membantu BAAK memperbaiki layanan. Waktu pengisian sekitar 3 menit.',
                'questions' => [
                    ['rating', 'Kemudahan pengurusan administrasi akademik', null, true],
                    ['rating', 'Kecepatan respons petugas BAAK', null, true],
                    ['rating', 'Kejelasan informasi jadwal dan perkuliahan', null, true],
                    ['rating', 'Kenyamanan fasilitas ruang kuliah', null, true],
                    ['pilihan', 'Layanan mana yang paling perlu ditingkatkan?', ['Administrasi akademik', 'Fasilitas kelas', 'Perpustakaan', 'Aplikasi Aksaraloka'], true],
                    ['pilihan_ganda', 'Kanal informasi apa yang kamu gunakan?', ['Aplikasi Aksaraloka', 'Grup WhatsApp', 'Papan pengumuman', 'Media sosial kampus'], true],
                    ['teks', 'Saran untuk perbaikan layanan', null, false],
                ],
            ],
            [
                'title' => 'Evaluasi Dosen Semester Ganjil 2026/2027', 'category' => 'evaluasi_dosen', 'audience' => ['student'],
                'opens_on' => today()->subDays(1), 'closes_on' => today()->addDays(20), 'anonymous' => true, 'published' => true, 'responses' => 4,
                'description' => 'Penilaian terhadap proses pembelajaran. Jawaban anonim dan hanya dibaca dalam bentuk rekap.',
                'questions' => [
                    ['rating', 'Dosen menjelaskan materi dengan jelas', null, true],
                    ['rating', 'Dosen hadir dan mulai kuliah tepat waktu', null, true],
                    ['rating', 'Dosen terbuka terhadap pertanyaan mahasiswa', null, true],
                    ['rating', 'Penilaian tugas dan ujian dilakukan adil', null, true],
                    ['teks', 'Komentar untuk dosen', null, false],
                ],
            ],
            [
                'title' => 'Survey Kepuasan Dosen terhadap Sistem Akademik', 'category' => 'kepuasan', 'audience' => ['teacher', 'curriculum'],
                'opens_on' => today()->subDays(2), 'closes_on' => today()->addDays(10), 'anonymous' => false, 'published' => true, 'responses' => 0,
                'description' => 'Bantu kami menyempurnakan fitur presensi, jadwal, dan rekap untuk dosen.',
                'questions' => [
                    ['rating', 'Kemudahan mengisi presensi kelas', null, true],
                    ['rating', 'Kemudahan melihat jadwal mengajar', null, true],
                    ['pilihan', 'Fitur apa yang paling kamu butuhkan?', ['Rekap per mata kuliah', 'Notifikasi mahasiswa alpa', 'Unggah materi', 'Penilaian'], true],
                ],
            ],
            [
                'title' => 'Survey Fasilitas Kampus', 'category' => 'umum', 'audience' => ['student', 'teacher'],
                'opens_on' => today()->addDays(7), 'closes_on' => today()->addDays(21), 'anonymous' => false, 'published' => false, 'responses' => 0,
                'description' => null,
                'questions' => [
                    ['rating', 'Kebersihan fasilitas kampus', null, true],
                    ['teks', 'Fasilitas yang perlu ditambah', null, false],
                ],
            ],
        ];

        foreach ($surveyDefinitions as $definition) {
            $survey = Survey::create([
                'tenant_id' => $tenant->id, 'created_by' => $adminUser->id, 'category' => $definition['category'], 'title' => $definition['title'],
                'description' => $definition['description'], 'audience' => $definition['audience'], 'opens_on' => $definition['opens_on'],
                'closes_on' => $definition['closes_on'], 'is_anonymous' => $definition['anonymous'], 'is_published' => $definition['published'],
            ]);

            $questions = collect($definition['questions'])->map(fn (array $row, int $position) => $survey->questions()->create([
                'position' => $position, 'type' => $row[0], 'text' => $row[1], 'options' => $row[2], 'is_required' => $row[3],
            ]));

            foreach ($extraStudents->take($definition['responses']) as $respondent) {
                $response = SurveyResponse::create([
                    'survey_id' => $survey->id, 'user_id' => $definition['anonymous'] ? null : $respondent->id,
                    'respondent_hash' => hash_hmac('sha256', "survey:{$survey->id}:user:{$respondent->id}", (string) config('app.key')),
                    'submitted_at' => now()->subHours(random_int(1, 70)),
                ]);

                foreach ($questions as $question) {
                    $value = match ($question->type->value) {
                        'rating' => (string) random_int(3, 5),
                        'pilihan' => $question->options[array_rand($question->options)],
                        'pilihan_ganda' => array_values(array_intersect_key($question->options, array_flip((array) array_rand($question->options, 2)))),
                        default => ['Tolong tambah stopkontak di kelas.', 'Layanan sudah baik, pertahankan.', 'Jadwal sebaiknya lebih awal diumumkan.'][array_rand([0, 1, 2])],
                    };

                    SurveyAnswer::create([
                        'survey_response_id' => $response->id, 'survey_question_id' => $question->id,
                        'answer' => is_array($value) ? null : $value, 'choices' => is_array($value) ? $value : null,
                    ]);
                }
            }
        }

        // Masukan dan komplain contoh dengan satu percakapan.
        $lecturerUser = User::where('email', 'dosen@aksaraloka.test')->first();

        collect([
            [$studentUser, 'komplain', 'fasilitas', 'AC Ruang 3.01 tidak berfungsi', 'Sudah dua minggu pendingin ruangan di Ruang 3.01 mati sehingga kelas siang sangat panas dan sulit berkonsentrasi.', false, 'diproses', true],
            [$studentUser, 'masukan', 'akademik', 'Usulan rekaman kuliah untuk mata kuliah praktikum', 'Mohon tersedia rekaman atau materi tambahan agar mahasiswa yang berhalangan hadir bisa mengejar ketertinggalan.', false, 'baru', false],
            [$guardianUser, 'komplain', 'keuangan', 'Rincian biaya UKT belum jelas', 'Kami kesulitan memahami rincian komponen UKT semester ini. Mohon dijelaskan atau disediakan rinciannya.', false, 'selesai', true],
            [$lecturerUser, 'masukan', 'aplikasi', 'Tambahkan unduhan rekap presensi per mata kuliah', 'Akan membantu bila rekap kehadiran per mata kuliah bisa diunduh langsung dari halaman presensi.', false, 'baru', false],
            [$studentUser, 'komplain', 'dosen', 'Jadwal kuliah sering diundur mendadak', 'Beberapa kali perkuliahan diundur tanpa pemberitahuan sehingga mahasiswa menunggu lama.', true, 'baru', false],
        ])->each(function (array $row) use ($tenant, $adminUser): void {
            $ticket = FeedbackTicket::create([
                'tenant_id' => $tenant->id, 'user_id' => $row[0]->id, 'type' => $row[1], 'category' => $row[2],
                'subject' => $row[3], 'body' => $row[4], 'is_anonymous' => $row[5], 'status' => $row[6],
                'handled_by' => $row[7] ? $adminUser->id : null, 'resolved_at' => $row[6] === 'selesai' ? now()->subDay() : null,
            ]);
            $ticket->update(['number' => sprintf('MK-%d-%04d', now()->year, $ticket->id)]);

            if ($row[7]) {
                FeedbackReply::create(['feedback_ticket_id' => $ticket->id, 'user_id' => $adminUser->id, 'body' => 'Terima kasih atas laporannya. Tim sarana sudah kami hubungi dan akan memeriksa dalam beberapa hari.']);
            }
        });

        // Semester sebelumnya (arsip, terkunci): kelas yang sama dengan mata kuliah semester 2 pada Selasa dan Kamis.
        $previousTerm = AcademicTerm::create([
            'tenant_id' => $tenant->id, 'name' => '2025/2026', 'semester' => 'Genap',
            'starts_at' => '2026-02-09', 'ends_at' => '2026-06-26', 'is_active' => false, 'is_locked' => true,
        ]);

        $previousSubjects = collect([
            ['Pengantar Teknologi Informasi', 'IF102', 3],
            ['Matematika Diskrit', 'MK102', 3],
            ['Bahasa Inggris Akademik', 'UM101', 2],
        ])->map(fn (array $row) => Subject::create([
            'tenant_id' => $tenant->id, 'name' => $row[0], 'code' => $row[1], 'credits' => $row[2], 'semester' => 2, 'program_id' => $informatics->id,
        ]));

        foreach ($groups->values() as $groupIndex => $group) {
            $previousGroup = StudyGroup::create([
                'tenant_id' => $tenant->id, 'academic_term_id' => $previousTerm->id, 'program_id' => $informatics->id,
                'name' => $group->name, 'homeroom_teacher_id' => $group->homeroom_teacher_id,
            ]);

            $group->activeStudents->each(fn (Student $student) => $previousGroup->members()->create([
                'student_id' => $student->id, 'joined_at' => '2026-02-09', 'left_at' => '2026-07-01',
            ]));

            foreach ($previousSubjects as $subjectIndex => $subject) {
                $assignment = TeachingAssignment::create([
                    'tenant_id' => $tenant->id, 'academic_term_id' => $previousTerm->id, 'teacher_id' => $teachers[$subjectIndex]->id,
                    'subject_id' => $subject->id, 'study_group_id' => $previousGroup->id, 'hours_per_week' => 2,
                ]);

                foreach ([2, $subjectIndex === 2 ? 6 : 4] as $day) {
                    $period = (($subjectIndex + $groupIndex) % 3) + 1;
                    $slot = $slots->first(fn (TimeSlot $candidate) => $candidate->day === $day && $candidate->period_number === $period);
                    Schedule::create([
                        'tenant_id' => $tenant->id, 'teaching_assignment_id' => $assignment->id,
                        'time_slot_id' => $slot->id, 'room_id' => $rooms[$groupIndex]->id,
                    ]);
                }
            }
        }

        // Riwayat presensi lima minggu terakhir (tanpa hari ini) agar rekap dan syarat ujian 75% terlihat.
        $dates = collect(range(1, 35))->map(fn (int $back) => today()->subDays($back))->filter(fn ($date) => ! $date->isSunday());
        $demoStudentId = $students->first()->id;
        $skippers = $students->filter(fn (Student $student, int $index) => $index % 15 === 7)->pluck('id');

        foreach ($schedules as $schedule) {
            $schedule->load('timeSlot', 'assignment.studyGroup.activeStudents');
            $group = $schedule->assignment->studyGroup;

            foreach ($dates->filter(fn ($date) => $date->dayOfWeekIso === $schedule->timeSlot->day) as $date) {
                $session = AttendanceSession::create([
                    'tenant_id' => $tenant->id, 'schedule_id' => $schedule->id, 'study_group_id' => $group->id, 'date' => $date,
                    'mode' => 'manual', 'opened_by' => $schedule->assignment->teacher->user_id,
                    'opened_at' => $date->copy()->setTime(7, 30), 'closed_at' => $date->copy()->setTime(9, 10),
                ]);

                foreach ($group->activeStudents as $student) {
                    $roll = random_int(1, 100);
                    $status = match (true) {
                        $student->id === $demoStudentId && $schedule->assignment->subject->code === 'MK101' && $roll <= 45 => AttendanceStatus::Absent,
                        $student->id === $demoStudentId && $schedule->assignment->subject->code === 'IF201' && $roll <= 15 => AttendanceStatus::Sick,
                        $skippers->contains($student->id) && $roll <= 60 => AttendanceStatus::Absent,
                        $roll <= 90 => AttendanceStatus::Present,
                        $roll <= 94 => AttendanceStatus::Sick,
                        $roll <= 97 => AttendanceStatus::Excused,
                        default => AttendanceStatus::Absent,
                    };

                    AttendanceRecord::create([
                        'attendance_session_id' => $session->id, 'student_id' => $student->id,
                        'status' => $status, 'method' => 'manual', 'recorded_at' => $date,
                        'recorded_by' => $schedule->assignment->teacher->user_id,
                    ]);
                }
            }
        }
    }
}
