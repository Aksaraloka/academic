<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Guardian;
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
     * Data contoh sekolah (kata sandi semua akun: "password").
     */
    public function run(): void
    {
        $tenant = Tenant::create([
            'name' => 'SMP Aksara Nusantara',
            'subdomain' => 'aksara-nusantara',
            'level' => 'school',
            'settings' => ['attendance_mode' => 'both'],
        ]);

        $account = fn (string $name, string $email, Role $role): User => User::create([
            'tenant_id' => $tenant->id, 'name' => $name, 'email' => $email, 'role' => $role, 'password' => 'password',
        ]);

        $account('Admin TU', 'admin@aksaraloka.test', Role::Admin);
        $account('Dra. Kepala Sekolah', 'kepsek@aksaraloka.test', Role::Principal);

        $teachers = collect([
            ['Budi Santoso', 'guru@aksaraloka.test', Role::Teacher],
            ['Sari Wulandari', 'sari@aksaraloka.test', Role::Teacher],
            ['Rina Kurniasih', 'kurikulum@aksaraloka.test', Role::Curriculum],
            ['Agus Piket', 'piket@aksaraloka.test', Role::Duty],
        ])->map(function (array $row, int $index) use ($account): Teacher {
            $user = $account($row[0], $row[1], $row[2]);

            return Teacher::create(['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'name' => $row[0], 'nip' => '1980010120100'.$index]);
        });

        $term = AcademicTerm::create([
            'tenant_id' => $tenant->id, 'name' => '2026/2027', 'semester' => 'Ganjil',
            'starts_at' => '2026-07-13', 'ends_at' => '2026-12-19', 'is_active' => true,
        ]);

        $groups = collect(['7A' => $teachers[0], '7B' => $teachers[1]])->map(fn (Teacher $homeroom, string $name) => StudyGroup::create([
            'tenant_id' => $tenant->id, 'academic_term_id' => $term->id, 'name' => $name, 'homeroom_teacher_id' => $homeroom->id,
        ]));

        $subjects = collect(['Matematika', 'Bahasa Indonesia', 'IPA'])->map(fn (string $name) => Subject::create(['tenant_id' => $tenant->id, 'name' => $name]));
        $rooms = collect(['R. 7A', 'R. 7B'])->map(fn (string $name) => Room::create(['tenant_id' => $tenant->id, 'name' => $name]));

        $periods = [[1, '07:00', '07:40'], [2, '07:40', '08:20'], [3, '08:20', '09:00'], [4, '09:15', '09:55']];
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
                    'name' => fake('id_ID')->name(),
                    'nis' => (string) (1000 + $groupIndex * 100 + $i),
                    'gender' => $i % 2 === 0 ? 'P' : 'L',
                    'status' => 'aktif',
                    'entry_date' => '2026-07-13',
                ]);
                $group->members()->create(['student_id' => $student->id, 'joined_at' => '2026-07-13']);
                $students->push($student);
            }
        }

        // Akun contoh siswa dan orang tua.
        $studentUser = $account($students->first()->name, 'siswa@aksaraloka.test', Role::Student);
        $students->first()->update(['user_id' => $studentUser->id]);

        $guardianUser = $account('Orang Tua Contoh', 'ortu@aksaraloka.test', Role::Guardian);
        $guardian = Guardian::create(['tenant_id' => $tenant->id, 'user_id' => $guardianUser->id, 'name' => $guardianUser->name, 'relation' => 'Ibu']);
        $guardian->students()->attach($students->first()->id);

        // Jadwal: tiap rombel tiap hari 2 pelajaran berurutan.
        foreach ($groups->values() as $groupIndex => $group) {
            foreach ($subjects as $subjectIndex => $subject) {
                $assignment = TeachingAssignment::create([
                    'tenant_id' => $tenant->id, 'academic_term_id' => $term->id,
                    'teacher_id' => $teachers[$subjectIndex]->id,
                    'subject_id' => $subject->id, 'study_group_id' => $group->id, 'hours_per_week' => 5,
                ]);

                foreach ([1, 2, 3, 4, 5] as $day) {
                    $slot = $slots->first(fn (TimeSlot $candidate) => $candidate->day === $day && $candidate->period_number === (($subjectIndex + $groupIndex) % 3) + 1);
                    Schedule::create(['tenant_id' => $tenant->id, 'teaching_assignment_id' => $assignment->id, 'time_slot_id' => $slot->id, 'room_id' => $rooms[$groupIndex]->id]);
                }
            }
        }

        // Riwayat presensi 5 hari sekolah terakhir untuk rekap.
        $history = collect(range(1, 7))->map(fn (int $back) => today()->subDays($back))->filter(fn ($date) => $date->isWeekday())->take(5);

        foreach ($history as $date) {
            foreach ($groups as $group) {
                $session = AttendanceSession::create([
                    'tenant_id' => $tenant->id, 'study_group_id' => $group->id, 'date' => $date,
                    'mode' => 'daily', 'opened_by' => $group->homeroomTeacher->user_id, 'opened_at' => $date->copy()->setTime(7, 0), 'closed_at' => $date->copy()->setTime(7, 10),
                ]);

                foreach ($group->activeStudents as $student) {
                    $roll = random_int(1, 100);
                    $status = match (true) {
                        $roll <= 85 => AttendanceStatus::Present,
                        $roll <= 90 => AttendanceStatus::Late,
                        $roll <= 94 => AttendanceStatus::Sick,
                        $roll <= 97 => AttendanceStatus::Excused,
                        default => AttendanceStatus::Absent,
                    };

                    AttendanceRecord::create([
                        'attendance_session_id' => $session->id, 'student_id' => $student->id,
                        'status' => $status, 'method' => 'manual', 'recorded_at' => $date, 'recorded_by' => $group->homeroomTeacher->user_id,
                    ]);
                }
            }
        }
    }
}
