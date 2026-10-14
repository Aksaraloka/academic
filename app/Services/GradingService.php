<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\GradeKind;
use App\Enums\GradeRevisionStatus;
use App\Enums\Role;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GradeRevision;
use App\Models\Student;
use App\Models\StudyGroupMember;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Support\GradeScale;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GradingService
{
    /** @var array<string, string> */
    public const FIELDS = ['assignment' => 'assignment_score', 'midterm' => 'midterm_score', 'final' => 'final_exam_score'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function scale(User $user): GradeScale
    {
        return GradeScale::for($user->tenant);
    }

    /**
     * Batas akhir input nilai: yang paling akhir antara batas semester dan perpanjangan untuk penugasan ini.
     */
    public function deadlineFor(TeachingAssignment $assignment): ?CarbonImmutable
    {
        $dates = array_filter([
            $assignment->academicTerm?->grading_closes_on,
            $assignment->grading_extended_until,
        ]);

        return $dates === [] ? null : CarbonImmutable::parse(max($dates));
    }

    public function isPastDeadline(TeachingAssignment $assignment): bool
    {
        $deadline = $this->deadlineFor($assignment);

        return $deadline !== null && today()->gt($deadline);
    }

    /**
     * Alasan input ditolak, atau null bila dosen boleh mengisi atau mengubah nilai.
     */
    public function inputBlockReason(TeachingAssignment $assignment, User $actor): ?string
    {
        if ($actor->teacher?->id !== $assignment->teacher_id) {
            return 'Hanya dosen pengampu yang bisa mengisi nilai kelas ini.';
        }

        if ($assignment->areGradesLocked()) {
            return 'Nilai sudah dikunci. Perubahan harus lewat pengajuan revisi dan persetujuan Kaprodi.';
        }

        if ($assignment->academicTerm?->is_locked) {
            return 'Semester ini sudah dikunci.';
        }

        if ($this->isPastDeadline($assignment)) {
            return 'Batas waktu input nilai sudah lewat. Hubungi BAAK untuk perpanjangan.';
        }

        return null;
    }

    /**
     * Mahasiswa yang dinilai: anggota kelas pada semester itu, peserta dengan KRS disetujui dari kelas lain, dan mahasiswa yang mengulang dan sudah punya baris nilai.
     *
     * @return Collection<int, Student>
     */
    public function roster(TeachingAssignment $assignment): Collection
    {
        $term = $assignment->academicTerm;

        $memberIds = StudyGroupMember::query()
            ->where('study_group_id', $assignment->study_group_id)
            ->whereDate('joined_at', '<=', $term->ends_at)
            ->where(fn ($query) => $query->whereNull('left_at')->orWhereDate('left_at', '>', $term->starts_at))
            ->pluck('student_id');

        $extraIds = $assignment->grades()->where('kind', GradeKind::Regular)->pluck('student_id');

        $enrolledIds = Enrollment::query()
            ->where('status', EnrollmentStatus::Approved)
            ->whereHas('items', fn ($query) => $query->where('teaching_assignment_id', $assignment->id))
            ->pluck('student_id');

        return Student::query()->whereIn('id', $memberIds->merge($extraIds)->merge($enrolledIds)->unique())->orderBy('name')->get();
    }

    /**
     * Persentase kehadiran mahasiswa pada kuliah ini (0–100), dihitung dari presensi dosen.
     *
     * @param  Collection<int, int>|array<int, int>  $studentIds
     * @return array<int, float>
     */
    public function attendanceScores(TeachingAssignment $assignment, Collection|array $studentIds): array
    {
        $rows = AttendanceRecord::query()
            ->whereIn('student_id', $studentIds)
            ->whereHas('session.schedule', fn ($query) => $query->where('teaching_assignment_id', $assignment->id))
            ->get(['student_id', 'status'])
            ->groupBy('student_id');

        $scores = [];

        foreach ($studentIds as $studentId) {
            $records = $rows->get($studentId, collect());
            $attended = $records->filter(fn ($record) => in_array($record->status->value, ['hadir', 'terlambat'], true))->count();
            $scores[$studentId] = $records->isEmpty() ? 0.0 : round($attended / $records->count() * 100, 2);
        }

        return $scores;
    }

    /**
     * Ubah bobot penilaian kelas ini. Jumlahnya harus 100 dan hanya bisa selama nilai belum dikunci.
     *
     * @param  array<string, int|string>  $weights
     */
    public function saveWeights(TeachingAssignment $assignment, User $actor, array $weights): void
    {
        $this->guardInput($assignment, $actor);

        $clean = [];

        foreach (array_keys(GradeScale::COMPONENTS) as $component) {
            $value = $weights[$component] ?? null;

            if (! is_numeric($value) || $value < 0 || $value > 100 || (int) $value != $value) {
                throw ValidationException::withMessages(["weights.{$component}" => 'Bobot harus bilangan bulat 0–100.']);
            }

            $clean[$component] = (int) $value;
        }

        if (array_sum($clean) !== 100) {
            throw ValidationException::withMessages(['weights' => 'Jumlah bobot harus 100 (sekarang '.array_sum($clean).').']);
        }

        $assignment->update([
            'weight_assignment' => $clean['assignment'],
            'weight_midterm' => $clean['midterm'],
            'weight_final' => $clean['final'],
            'weight_attendance' => $clean['attendance'],
        ]);

        $this->recalculateAll($assignment, $actor);
    }

    /**
     * Simpan draf nilai (tugas, UTS, UAS) untuk banyak mahasiswa sekaligus.
     *
     * @param  array<int|string, array<string, mixed>>  $scores  student_id => [assignment, midterm, final]
     */
    public function saveScores(TeachingAssignment $assignment, User $actor, array $scores): int
    {
        $this->guardInput($assignment, $actor);

        $roster = $this->roster($assignment)->keyBy('id');
        $errors = [];
        $parsed = [];

        foreach ($scores as $studentId => $row) {
            if (! $roster->has((int) $studentId)) {
                continue;
            }

            foreach (array_keys(self::FIELDS) as $field) {
                $value = $row[$field] ?? null;

                if ($value === null || $value === '') {
                    $parsed[(int) $studentId][$field] = null;

                    continue;
                }

                if (! is_numeric($value) || $value < 0 || $value > 100) {
                    $errors["scores.{$studentId}.{$field}"] = 'Nilai harus angka 0–100.';

                    continue;
                }

                $parsed[(int) $studentId][$field] = round((float) $value, 2);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $scale = $this->scale($actor);
        $weights = $scale->weightsFor($assignment);
        $attendance = $this->attendanceScores($assignment, array_keys($parsed));

        DB::transaction(function () use ($assignment, $parsed, $attendance, $scale, $weights): void {
            foreach ($parsed as $studentId => $row) {
                $this->storeRegular($assignment, $studentId, $row, $attendance[$studentId], $scale, $weights);
            }
        });

        return count($parsed);
    }

    /**
     * Kunci nilai: semua mahasiswa harus punya tiga komponen. Setelah dikunci, nilai tampil di KHS mahasiswa.
     */
    public function lock(TeachingAssignment $assignment, User $actor): void
    {
        $this->guardInput($assignment, $actor);

        $roster = $this->roster($assignment);
        $grades = $assignment->grades()->where('kind', GradeKind::Regular)->get()->keyBy('student_id');
        $incomplete = $roster->filter(fn (Student $student): bool => ! ($grades->get($student->id)?->hasComponents() ?? false));

        if ($roster->isEmpty()) {
            throw ValidationException::withMessages(['lock' => 'Belum ada mahasiswa yang dinilai pada kelas ini.']);
        }

        if ($incomplete->isNotEmpty()) {
            throw ValidationException::withMessages(['lock' => $incomplete->count().' mahasiswa belum lengkap nilainya (mis. '.$incomplete->take(3)->pluck('name')->implode(', ').'). Lengkapi tugas, UTS, dan UAS sebelum mengunci.']);
        }

        DB::transaction(function () use ($assignment, $actor): void {
            $this->recalculateAll($assignment, $actor, refreshAttendance: true);
            $assignment->update(['grades_locked_at' => now(), 'grades_locked_by' => $actor->id]);
            $this->audit->log('grades.locked', $assignment, [], ['students' => $assignment->grades()->where('kind', GradeKind::Regular)->count()]);
        });
    }

    /**
     * Tambahkan mahasiswa yang mengulang mata kuliah ini (mengambil kuliah bersama kelas lain).
     */
    public function addRepeater(TeachingAssignment $assignment, User $actor, string $nim): Student
    {
        $this->guardInput($assignment, $actor);

        $student = Student::query()->where('nim', trim($nim))->first();

        if ($student === null) {
            throw ValidationException::withMessages(['nim' => 'NIM tidak ditemukan.']);
        }

        if ($this->roster($assignment)->contains('id', $student->id)) {
            throw ValidationException::withMessages(['nim' => 'Mahasiswa ini sudah ada di daftar nilai.']);
        }

        Grade::query()->create([
            'teaching_assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'tenant_id' => $assignment->tenant_id,
            'kind' => GradeKind::Regular,
            'attendance_score' => 0,
        ]);

        return $student;
    }

    /**
     * Nilai remedial untuk mahasiswa yang hurufnya berhak remedial. Hasil terbaik dipakai di KHS dan transkrip.
     */
    public function saveRemedial(TeachingAssignment $assignment, User $actor, int $studentId, mixed $score): Grade
    {
        if ($actor->teacher?->id !== $assignment->teacher_id) {
            throw ValidationException::withMessages(['remedial' => 'Hanya dosen pengampu yang bisa mengisi remedial.']);
        }

        if (! $assignment->areGradesLocked()) {
            throw ValidationException::withMessages(['remedial' => 'Remedial diisi setelah nilai reguler dikunci.']);
        }

        if ($assignment->academicTerm?->is_locked) {
            throw ValidationException::withMessages(['remedial' => 'Semester ini sudah dikunci.']);
        }

        if (! is_numeric($score) || $score < 0 || $score > 100) {
            throw ValidationException::withMessages(['remedial' => 'Nilai remedial harus angka 0–100.']);
        }

        $regular = $assignment->grades()->where('kind', GradeKind::Regular)->where('student_id', $studentId)->first();

        if ($regular === null || ! in_array($regular->letter, $this->scale($actor)->remedialLetters, true)) {
            throw ValidationException::withMessages(['remedial' => 'Mahasiswa ini tidak berhak mengikuti remedial.']);
        }

        $scale = $this->scale($actor);
        $total = round((float) $score, 2);
        $letter = $scale->letterFor($total);

        $grade = Grade::query()->updateOrCreate(
            ['teaching_assignment_id' => $assignment->id, 'student_id' => $studentId, 'kind' => GradeKind::Remedial],
            ['tenant_id' => $assignment->tenant_id, 'total_score' => $total, 'letter' => $letter, 'points' => GradeScale::pointsFor($letter)],
        );

        $this->audit->log('grades.remedial_saved', $grade, [], ['total' => $total, 'letter' => $letter]);

        return $grade;
    }

    /**
     * Dosen mengajukan perubahan nilai yang sudah dikunci. Perlu persetujuan Kaprodi.
     *
     * @param  array<string, mixed>  $new  assignment, midterm, final
     */
    public function requestRevision(Grade $grade, User $actor, array $new, string $reason): GradeRevision
    {
        $assignment = $grade->assignment;

        if ($actor->teacher?->id !== $assignment->teacher_id) {
            throw ValidationException::withMessages(['revision' => 'Hanya dosen pengampu yang bisa mengajukan revisi.']);
        }

        if (! $assignment->areGradesLocked() || $grade->kind !== GradeKind::Regular) {
            throw ValidationException::withMessages(['revision' => 'Revisi hanya untuk nilai reguler yang sudah dikunci.']);
        }

        if (blank($reason) || mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['revision' => 'Tuliskan alasan revisi (minimal 10 karakter).']);
        }

        if ($grade->revisions()->where('status', GradeRevisionStatus::Pending)->exists()) {
            throw ValidationException::withMessages(['revision' => 'Masih ada pengajuan revisi yang menunggu untuk nilai ini.']);
        }

        $clean = [];

        foreach (array_keys(self::FIELDS) as $field) {
            $value = $new[$field] ?? null;

            if (! is_numeric($value) || $value < 0 || $value > 100) {
                throw ValidationException::withMessages(["revision.{$field}" => 'Nilai harus angka 0–100.']);
            }

            $clean[$field] = round((float) $value, 2);
        }

        $old = $grade->scores();

        if ($clean['assignment'] === $old['assignment'] && $clean['midterm'] === $old['midterm'] && $clean['final'] === $old['final']) {
            throw ValidationException::withMessages(['revision' => 'Tidak ada perubahan nilai.']);
        }

        $revision = GradeRevision::query()->create([
            'tenant_id' => $grade->tenant_id,
            'grade_id' => $grade->id,
            'requested_by' => $actor->id,
            'reason' => trim($reason),
            'old_scores' => $old,
            'new_scores' => $clean + $this->previewResult($grade, $clean, $actor),
            'status' => GradeRevisionStatus::Pending,
        ]);

        $this->audit->log('grades.revision_requested', $revision, [], ['grade_id' => $grade->id]);

        return $revision;
    }

    /**
     * Kaprodi menyetujui atau menolak revisi. Persetujuan langsung memperbarui nilai dan huruf mutu.
     */
    public function decideRevision(GradeRevision $revision, User $decider, bool $approve, ?string $note = null): GradeRevision
    {
        if (! $decider->hasRole(Role::Principal)) {
            throw ValidationException::withMessages(['decision' => 'Hanya Kaprodi/Pimpinan yang bisa memutuskan revisi nilai.']);
        }

        if ($revision->status !== GradeRevisionStatus::Pending) {
            throw ValidationException::withMessages(['decision' => 'Pengajuan ini sudah diputuskan.']);
        }

        if (! $approve && blank($note)) {
            throw ValidationException::withMessages(['note' => 'Alasan penolakan wajib diisi.']);
        }

        DB::transaction(function () use ($revision, $decider, $approve, $note): void {
            $grade = $revision->grade;

            if ($approve) {
                $new = $revision->new_scores;
                $scale = $this->scale($decider);
                $weights = $scale->weightsFor($grade->assignment);
                $this->storeRegular($grade->assignment, $grade->student_id, [
                    'assignment' => (float) $new['assignment'], 'midterm' => (float) $new['midterm'], 'final' => (float) $new['final'],
                ], (float) $grade->attendance_score, $scale, $weights);
            }

            $revision->update([
                'status' => $approve ? GradeRevisionStatus::Approved : GradeRevisionStatus::Rejected,
                'decided_by' => $decider->id,
                'decided_at' => now(),
                'decision_note' => $note ?: null,
            ]);

            $this->audit->log('grades.revision_decided', $revision, ['status' => 'menunggu'], ['status' => $revision->status->value]);
        });

        return $revision->refresh();
    }

    /**
     * Total dan huruf yang akan dihasilkan oleh nilai baru (untuk pratinjau pada pengajuan revisi).
     *
     * @param  array<string, float>  $clean
     * @return array{total: ?float, letter: ?string}
     */
    private function previewResult(Grade $grade, array $clean, User $actor): array
    {
        $scale = $this->scale($actor);
        $total = GradeScale::total($clean + ['attendance' => (float) $grade->attendance_score], $scale->weightsFor($grade->assignment));

        return ['total' => $total, 'letter' => $scale->letterFor($total)];
    }

    /**
     * @param  array<string, ?float>  $row  assignment, midterm, final
     * @param  array<string, int>  $weights
     */
    private function storeRegular(TeachingAssignment $assignment, int $studentId, array $row, float $attendance, GradeScale $scale, array $weights): Grade
    {
        $scores = [
            'assignment' => $row['assignment'] ?? null,
            'midterm' => $row['midterm'] ?? null,
            'final' => $row['final'] ?? null,
            'attendance' => $attendance,
        ];

        $total = GradeScale::total($scores, $weights);
        $letter = $scale->letterFor($total);

        return Grade::query()->updateOrCreate(
            ['teaching_assignment_id' => $assignment->id, 'student_id' => $studentId, 'kind' => GradeKind::Regular],
            [
                'tenant_id' => $assignment->tenant_id,
                'assignment_score' => $scores['assignment'],
                'midterm_score' => $scores['midterm'],
                'final_exam_score' => $scores['final'],
                'attendance_score' => $attendance,
                'total_score' => $total,
                'letter' => $letter,
                'points' => GradeScale::pointsFor($letter),
            ],
        );
    }

    /**
     * Hitung ulang total dan huruf semua nilai reguler (bobot berubah, atau kehadiran diperbarui saat penguncian).
     */
    private function recalculateAll(TeachingAssignment $assignment, User $actor, bool $refreshAttendance = false): void
    {
        $scale = $this->scale($actor);
        $weights = $scale->weightsFor($assignment->refresh());
        $grades = $assignment->grades()->where('kind', GradeKind::Regular)->get();
        $attendance = $refreshAttendance ? $this->attendanceScores($assignment, $grades->pluck('student_id')) : [];

        foreach ($grades as $grade) {
            $this->storeRegular($assignment, $grade->student_id, [
                'assignment' => $grade->assignment_score === null ? null : (float) $grade->assignment_score,
                'midterm' => $grade->midterm_score === null ? null : (float) $grade->midterm_score,
                'final' => $grade->final_exam_score === null ? null : (float) $grade->final_exam_score,
            ], $attendance[$grade->student_id] ?? (float) $grade->attendance_score, $scale, $weights);
        }
    }

    private function guardInput(TeachingAssignment $assignment, User $actor): void
    {
        $reason = $this->inputBlockReason($assignment, $actor);

        if ($reason !== null) {
            throw ValidationException::withMessages(['input' => $reason]);
        }
    }
}
