<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Faculty;
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

class DatabaseSeeder extends Seeder
{
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
