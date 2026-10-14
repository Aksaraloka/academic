<?php

namespace Database\Seeders;

use App\Models\AcademicTerm;
use App\Models\Grade;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Tenant;
use App\Support\GradeScale;
use Database\Seeders\Data\Curriculum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Mahasiswa angkatan 2022–2026 pada tiga program studi dengan riwayat nilai terkunci tiap semester,
 * remedial, dan mata kuliah yang diulang. Dipanggil dari DatabaseSeeder setelah data inti dibuat.
 */
class AcademicHistorySeeder extends Seeder
{
    /** Angkatan yang dibuat dan jumlah mahasiswa per program studi. */
    private const BATCHES = [2022 => 12, 2023 => 12, 2024 => 10, 2025 => 12, 2026 => 10];

    private const PROGRAM_DIGITS = ['TI' => 1, 'SI' => 2, 'MJ' => 3];

    /** Indeks semester akademik: 0 = 2022/2023 Ganjil … 7 = 2025/2026 Genap, 8 = semester berjalan. */
    private const CURRENT_TERM_INDEX = 8;

    /**
     * @param  Collection<int, Teacher>  $teachers
     */
    public function seed(Tenant $tenant, Collection $teachers): void
    {
        $scale = GradeScale::for($tenant);
        $terms = $this->terms($tenant);
        $programs = Program::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->get()->keyBy('code');
        $curriculum = Curriculum::all();

        $subjects = [];
        $groups = [];
        $assignments = [];

        $subjectFor = function (string $code, int $semester, int $position) use (&$subjects, $curriculum, $programs, $tenant): Subject {
            $key = "{$code}.{$semester}.{$position}";

            if (! isset($subjects[$key])) {
                [$name, $credits] = $curriculum[$code][$semester][$position];
                $subjects[$key] = Subject::create([
                    'tenant_id' => $tenant->id, 'program_id' => $programs[$code]->id, 'name' => $name,
                    'code' => sprintf('%s%d%02d', $code, $semester, $position + 1), 'credits' => $credits, 'semester' => $semester,
                ]);
            }

            return $subjects[$key];
        };

        $groupFor = function (string $code, int $batch, int $termIndex) use (&$groups, $programs, $terms, $tenant, $teachers): StudyGroup {
            $key = "{$code}.{$batch}.{$termIndex}";

            return $groups[$key] ??= StudyGroup::create([
                'tenant_id' => $tenant->id, 'academic_term_id' => $terms[$termIndex]->id, 'program_id' => $programs[$code]->id,
                'name' => "{$code}-{$batch}", 'homeroom_teacher_id' => $teachers[$batch % 2]->id,
            ]);
        };

        $assignmentFor = function (StudyGroup $group, Subject $subject, int $termIndex) use (&$assignments, $terms, $tenant, $teachers): TeachingAssignment {
            $key = "{$group->id}.{$subject->id}";

            if (! isset($assignments[$key])) {
                $teacher = $teachers[$subject->id % 3];
                $assignments[$key] = TeachingAssignment::create([
                    'tenant_id' => $tenant->id, 'academic_term_id' => $terms[$termIndex]->id, 'teacher_id' => $teacher->id,
                    'subject_id' => $subject->id, 'study_group_id' => $group->id, 'hours_per_week' => 2,
                ]);
                $assignments[$key]->update([
                    'grades_locked_at' => $terms[$termIndex]->ends_at->copy()->addDays(5)->setTime(10, 0), 'grades_locked_by' => $teacher->user_id,
                ]);
            }

            return $assignments[$key];
        };

        $clamp = fn (int|float $value): float => (float) max(0, min(100, round($value, 2)));

        $record = function (TeachingAssignment $assignment, Student $student, float $base) use ($scale, $clamp, $tenant): Grade {
            $scores = [
                'assignment' => $clamp($base + random_int(-6, 8)),
                'midterm' => $clamp($base + random_int(-10, 10)),
                'final' => $clamp($base + random_int(-12, 10)),
                'attendance' => (float) random_int(78, 100),
            ];
            $total = GradeScale::total($scores, $scale->weightsFor($assignment));
            $letter = $scale->letterFor($total);

            return Grade::create([
                'tenant_id' => $tenant->id, 'teaching_assignment_id' => $assignment->id, 'student_id' => $student->id, 'kind' => 'reguler',
                'assignment_score' => $scores['assignment'], 'midterm_score' => $scores['midterm'], 'final_exam_score' => $scores['final'],
                'attendance_score' => $scores['attendance'], 'total_score' => $total, 'letter' => $letter, 'points' => GradeScale::pointsFor($letter),
            ]);
        };

        $sequence = 200;

        foreach (self::BATCHES as $batch => $perProgram) {
            $currentSemester = (2026 - $batch) * 2 + 1;

            foreach (self::PROGRAM_DIGITS as $code => $digit) {
                // Angkatan 2024 Teknik Informatika sudah dibuat di DatabaseSeeder.
                if ($batch === 2024 && $code === 'TI') {
                    continue;
                }

                for ($i = 0; $i < $perProgram; $i++) {
                    $sequence++;
                    $student = $this->student($tenant, $programs[$code], $batch, sprintf('%02d%d%04d', $batch % 100, $digit, $sequence), $i);
                    $ability = random_int(1, 100) <= 12 ? random_int(84, 94) : random_int(58, 86);

                    $groupFor($code, $batch, self::CURRENT_TERM_INDEX)->members()->create(['student_id' => $student->id, 'joined_at' => $terms[self::CURRENT_TERM_INDEX]->starts_at]);

                    for ($semester = 1; $semester < $currentSemester; $semester++) {
                        $termIndex = ($batch - 2022) * 2 + ($semester - 1);
                        $group = $groupFor($code, $batch, $termIndex);

                        if (! $group->members()->where('student_id', $student->id)->exists()) {
                            $group->members()->create([
                                'student_id' => $student->id, 'joined_at' => $terms[$termIndex]->starts_at, 'left_at' => $terms[$termIndex]->ends_at,
                            ]);
                        }

                        foreach (array_keys($curriculum[$code][$semester]) as $position) {
                            $subject = $subjectFor($code, $semester, $position);
                            $assignment = $assignmentFor($group, $subject, $termIndex);
                            $failed = random_int(1, 100) <= 6;
                            $grade = $record($assignment, $student, $failed ? random_int(28, 50) : $ability + random_int(-10, 8));

                            if (! in_array($grade->letter, $scale->remedialLetters, true)) {
                                continue;
                            }

                            if (random_int(1, 100) <= 65) {
                                Grade::create([
                                    'tenant_id' => $tenant->id, 'teaching_assignment_id' => $assignment->id, 'student_id' => $student->id, 'kind' => 'remedial',
                                    'total_score' => $score = random_int(60, 78), 'letter' => $letter = $scale->letterFor($score), 'points' => GradeScale::pointsFor($letter),
                                ]);
                            } elseif ($termIndex < self::CURRENT_TERM_INDEX - 1) {
                                // Mengulang di semester berikutnya (kelas angkatan yang sama).
                                $retakeIndex = $termIndex + 1;
                                $record($assignmentFor($groupFor($code, $batch, $retakeIndex), $subject, $retakeIndex), $student, random_int(66, 84));
                            }
                        }
                    }

                    // Angkatan 2022 melengkapi SKS dengan pengakuan kredit agar mendekati syarat wisuda.
                    if ($batch === 2022) {
                        $student->update(['legacy_credits' => 30, 'legacy_quality_points' => round(30 * fake()->randomFloat(2, 2.8, 3.7), 2)]);
                    }
                }
            }
        }
    }

    /**
     * Semester akademik 2022/2023 Ganjil sampai sekarang; dua yang terakhir sudah dibuat DatabaseSeeder.
     *
     * @return array<int, AcademicTerm>
     */
    private function terms(Tenant $tenant): array
    {
        $terms = [];

        for ($index = 0; $index < self::CURRENT_TERM_INDEX; $index++) {
            $year = 2022 + intdiv($index, 2);
            $odd = $index % 2 === 0;
            $name = $year.'/'.($year + 1);
            $semester = $odd ? 'Ganjil' : 'Genap';

            $terms[$index] = AcademicTerm::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('name', $name)->where('semester', $semester)->first()
                ?? AcademicTerm::create([
                    'tenant_id' => $tenant->id, 'name' => $name, 'semester' => $semester,
                    'starts_at' => $odd ? "{$year}-09-05" : ($year + 1).'-02-09', 'ends_at' => $odd ? ($year + 1).'-01-15' : ($year + 1).'-06-26',
                    'is_active' => false, 'is_locked' => true,
                    'grading_closes_on' => $odd ? ($year + 1).'-01-29' : ($year + 1).'-07-10',
                ]);
        }

        $terms[self::CURRENT_TERM_INDEX] = AcademicTerm::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_active', true)->firstOrFail();

        return $terms;
    }

    private function student(Tenant $tenant, Program $program, int $batch, string $nim, int $index): Student
    {
        $migrant = $index % 4 === 1;
        $origin = $migrant
            ? [['Medan', 'Sumatera Utara'], ['Padang', 'Sumatera Barat'], ['Makassar', 'Sulawesi Selatan'], ['Pontianak', 'Kalimantan Barat'], ['Denpasar', 'Bali']][random_int(0, 4)]
            : ['Bandung', 'Jawa Barat'];

        return Student::create([
            'tenant_id' => $tenant->id, 'program_id' => $program->id, 'name' => fake('id_ID')->name(), 'nim' => $nim, 'batch_year' => $batch,
            'birth_place' => fake('id_ID')->city(), 'birth_date' => ($batch - 18 - random_int(0, 1)).'-'.sprintf('%02d-%02d', random_int(1, 12), random_int(1, 28)),
            'gender' => $index % 2 === 0 ? 'P' : 'L', 'religion' => ['Islam', 'Kristen Protestan', 'Katolik', 'Hindu', 'Buddha'][random_int(0, 4)],
            'nik' => '3273'.fake()->numerify('############'), 'address' => fake('id_ID')->streetAddress(),
            'city' => $origin[0], 'province' => $origin[1], 'postal_code' => fake()->numerify('#####'),
            'is_domicile_same' => ! $migrant,
            ...($migrant ? [
                'residence_type' => 'Kos/kontrakan', 'domicile_address' => 'Jl. '.fake('id_ID')->streetName().' No. '.random_int(1, 90).' (kos), Dago',
                'domicile_city' => 'Bandung', 'domicile_province' => 'Jawa Barat', 'domicile_postal_code' => '40135',
            ] : []),
            'phone' => '08'.fake()->numerify('##########'), 'previous_school' => 'SMA Negeri '.random_int(1, 20).' '.$origin[0],
            'admission_path' => ['SNBP', 'SNBT', 'Mandiri'][random_int(0, 2)], 'status' => 'aktif', 'entry_date' => "{$batch}-09-05",
        ]);
    }
}
