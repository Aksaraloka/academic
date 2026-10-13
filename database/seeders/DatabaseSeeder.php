<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Faculty;
use App\Models\GraduationNews;
use App\Models\GraduationPeriod;
use App\Models\GraduationRegistration;
use App\Models\Guardian;
use App\Models\Program;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\Subject;
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
     * Poster contoh berformat SVG (rasio 4:5) bertema merah Aksaraloka.
     */
    private function posterSvg(string $title, string $badge): string
    {
        $lines = array_map(fn (string $line): string => htmlspecialchars($line, ENT_XML1), explode("\n", wordwrap($title, 18, "\n", true)));
        $text = '';

        foreach ($lines as $index => $line) {
            $y = 520 + $index * 72;
            $text .= "<text x=\"60\" y=\"{$y}\" font-family=\"Arial, sans-serif\" font-size=\"58\" font-weight=\"700\" fill=\"#ffffff\">{$line}</text>";
        }

        $badge = htmlspecialchars($badge, ENT_XML1);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 1000" width="800" height="1000">
  <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#c62828"/><stop offset="1" stop-color="#7f1d1d"/></linearGradient></defs>
  <rect width="800" height="1000" fill="url(#g)"/>
  <circle cx="680" cy="140" r="220" fill="#ffffff" fill-opacity="0.10"/>
  <circle cx="90" cy="930" r="160" fill="#ffffff" fill-opacity="0.08"/>
  <rect x="60" y="120" width="330" height="56" rx="28" fill="#ffffff"/>
  <text x="90" y="158" font-family="Arial, sans-serif" font-size="26" font-weight="700" fill="#7f1d1d">{$badge}</text>
  {$text}
  <text x="60" y="900" font-family="Arial, sans-serif" font-size="30" fill="#ffffff" fill-opacity="0.9">Wisuda Aksaraloka Academic</text>
</svg>
SVG;
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

        foreach ([1, 2, 3, 4, 5] as $day) {
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
                    'gender' => $i % 2 === 0 ? 'P' : 'L',
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
        $guardian = Guardian::create(['tenant_id' => $tenant->id, 'user_id' => $guardianUser->id, 'name' => $guardianUser->name, 'relation' => 'Ibu']);
        $guardian->students()->attach($students->first()->id);

        // Jadwal: tiap kelas punya tiga mata kuliah, masing-masing dua kali seminggu, tanpa bentrok dosen.
        $schedules = collect();

        foreach ($groups->values() as $groupIndex => $group) {
            foreach ($subjects as $subjectIndex => $subject) {
                $assignment = TeachingAssignment::create([
                    'tenant_id' => $tenant->id, 'academic_term_id' => $term->id,
                    'teacher_id' => $teachers[$subjectIndex]->id,
                    'subject_id' => $subject->id, 'study_group_id' => $group->id, 'hours_per_week' => 2,
                ]);

                foreach ([1, 3] as $day) {
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
            ['Pendaftaran Wisuda Periode II Dibuka', 'Pendaftaran dibuka hingga '.today()->addDays(20)->translatedFormat('j F Y').'. Lengkapi berkas dan daftar lewat menu Pendaftaran.', 'PENDAFTARAN', true, 1],
            ['Gladi Bersih dan Pengambilan Toga', 'Gladi bersih wisuda dilaksanakan satu minggu sebelum hari-H di Auditorium Utama.', 'GLADI BERSIH', true, 3],
            ['Syarat dan Berkas Wisuda', 'Daftar lengkap persyaratan administrasi dan akademik bagi calon wisudawan.', 'PERSYARATAN', true, 6],
            ['Jadwal Foto Wisudawan', 'Jadwal sesi foto toga per program studi. Datang 15 menit lebih awal.', 'JADWAL FOTO', false, 9],
        ])->each(function (array $row, int $index) use ($tenant, $period, $adminUser): void {
            $path = "wisuda-posters/seed-{$tenant->id}-{$index}.svg";
            Storage::disk('local')->put($path, $this->posterSvg($row[0], $row[2]));

            GraduationNews::create([
                'tenant_id' => $tenant->id, 'graduation_period_id' => $period->id, 'created_by' => $adminUser?->id,
                'title' => $row[0], 'summary' => $row[1], 'body' => $row[1]."\n\nInformasi lebih lanjut dapat ditanyakan ke BAAK pada jam kerja.",
                'poster_path' => $path, 'is_published' => $row[3], 'published_at' => $row[3] ? now()->subDays($row[4]) : null,
            ]);
        });

        // Riwayat presensi dua minggu terakhir (tanpa hari ini) agar rekap dan syarat ujian 75% terlihat.
        $dates = collect(range(1, 14))->map(fn (int $back) => today()->subDays($back))->filter(fn ($date) => $date->isWeekday());
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
