<?php

namespace App\Services;

use App\Enums\AcademicEventType;
use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\AcademicTerm;
use App\Models\Enrollment;
use App\Models\EnrollmentItem;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Support\GradeScale;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kartu Rencana Studi: mahasiswa memilih mata kuliah yang dibuka semester itu, dosen wali (atau BAAK) menyetujui,
 * dan KRS yang disetujui menjadi daftar peserta kuliah untuk penilaian. Pengisian hanya sah selama masa KRS di kalender akademik.
 */
class EnrollmentService
{
    /** @var array<int, array{min_ips: float, credits: int}> beban SKS maksimal menurut IPS semester sebelumnya */
    public const CREDIT_LIMITS = [
        ['min_ips' => 3.0, 'credits' => 24],
        ['min_ips' => 2.5, 'credits' => 21],
        ['min_ips' => 2.0, 'credits' => 18],
        ['min_ips' => 0.0, 'credits' => 15],
    ];

    /** Beban SKS bagi mahasiswa yang belum punya IPS semester sebelumnya. */
    public const FIRST_SEMESTER_CREDITS = 20;

    public function __construct(
        private readonly AcademicCalendar $calendar,
        private readonly AcademicRecord $records,
        private readonly CurriculumService $curricula,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Kelas mahasiswa pada semester tersebut (kelas angkatannya).
     */
    public function groupFor(Student $student, AcademicTerm $term): ?StudyGroup
    {
        return StudyGroup::query()
            ->where('academic_term_id', $term->id)
            ->whereHas('members', fn ($query) => $query->where('student_id', $student->id)->whereNull('left_at'))
            ->first();
    }

    public function isWindowOpen(AcademicTerm $term): bool
    {
        return ! $term->is_locked && $this->calendar->isOpen($term, AcademicEventType::Enrollment);
    }

    /**
     * Masa KRS yang sedang atau akan berjalan, untuk ditampilkan ke mahasiswa.
     *
     * @return array{starts_on: CarbonInterface, ends_on: CarbonInterface}|null
     */
    public function window(AcademicTerm $term): ?array
    {
        $entry = $this->calendar->entries($term)->firstWhere('type', AcademicEventType::Enrollment);

        return $entry ? ['starts_on' => $entry['starts_on'], 'ends_on' => $entry['ends_on']] : null;
    }

    /**
     * SKS maksimal: ditentukan IPS semester terakhir yang nilainya sudah dikunci sebelum semester ini.
     */
    public function maxCredits(Student $student, AcademicTerm $term): int
    {
        $previous = $this->records->termsWithGrades($student)->first(fn (AcademicTerm $candidate): bool => $candidate->starts_at->lt($term->starts_at));

        $ips = $previous ? $this->records->khs($student, $previous)['ips'] : null;

        if ($ips === null) {
            return self::FIRST_SEMESTER_CREDITS;
        }

        foreach (self::CREDIT_LIMITS as $limit) {
            if ($ips >= $limit['min_ips']) {
                return $limit['credits'];
            }
        }

        return self::FIRST_SEMESTER_CREDITS;
    }

    /**
     * Mata kuliah yang dibuka untuk prodi mahasiswa pada semester itu, kelas sendiri lebih dulu.
     * Mata kuliah yang sudah lulus (di atas huruf remedial) tidak ditawarkan lagi.
     *
     * @return Collection<int, TeachingAssignment>
     */
    public function offerings(Student $student, AcademicTerm $term): Collection
    {
        $groupId = $this->groupFor($student, $term)?->id;
        $passed = $this->passedSubjectIds($student);
        $passedForPrerequisites = $this->curricula->passedForPrerequisites($student);
        $items = $this->curricula->itemsFor($student);

        $assignments = TeachingAssignment::query()
            ->with(['subject.prerequisites', 'teacher', 'studyGroup', 'schedules.timeSlot'])
            ->where('academic_term_id', $term->id)
            ->whereHas('subject', fn ($query) => $query->where('program_id', $student->program_id))
            ->get()
            ->reject(fn (TeachingAssignment $assignment): bool => $passed->contains($assignment->subject_id))
            ->when($items->isNotEmpty(), fn (Collection $all) => $all->filter(fn (TeachingAssignment $assignment): bool => $items->has($assignment->subject_id)));

        $seats = $this->seatsTaken($assignments->pluck('id')->all());

        return $assignments
            ->each(function (TeachingAssignment $assignment) use ($items, $passedForPrerequisites, $seats): void {
                $item = $items->get($assignment->subject_id);

                $assignment->setAttribute('krs_type', $item?->type);
                $assignment->setAttribute('krs_semester', $item?->semester ?? $assignment->subject->semester);
                $assignment->setAttribute('krs_unmet', $this->curricula->unmetPrerequisites($assignment->subject, $passedForPrerequisites));
                $assignment->setAttribute('krs_seats_taken', $seats[$assignment->id] ?? 0);
            })
            ->sortBy([
                fn (TeachingAssignment $a, TeachingAssignment $b): int => (int) ($b->study_group_id === $groupId) <=> (int) ($a->study_group_id === $groupId),
                fn (TeachingAssignment $a, TeachingAssignment $b): int => $a->krs_semester <=> $b->krs_semester,
                fn (TeachingAssignment $a, TeachingAssignment $b): int => strcmp($a->subject->name, $b->subject->name),
            ])
            ->values();
    }

    /**
     * Jumlah kursi terpakai per penugasan kuliah: KRS yang diajukan atau disetujui.
     *
     * @param  array<int, int>  $assignmentIds
     * @return array<int, int>
     */
    public function seatsTaken(array $assignmentIds, ?int $exceptEnrollmentId = null): array
    {
        return EnrollmentItem::query()
            ->whereIn('teaching_assignment_id', $assignmentIds)
            ->whereHas('enrollment', fn ($query) => $query->whereIn('status', [EnrollmentStatus::Submitted, EnrollmentStatus::Approved])
                ->when($exceptEnrollmentId, fn ($inner) => $inner->whereKeyNot($exceptEnrollmentId)))
            ->selectRaw('teaching_assignment_id, count(*) as taken')
            ->groupBy('teaching_assignment_id')
            ->pluck('taken', 'teaching_assignment_id')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    /**
     * @return Collection<int, int> id mata kuliah yang sudah lulus dengan huruf di atas batas remedial
     */
    public function passedSubjectIds(Student $student): Collection
    {
        $remedial = GradeScale::for($student->tenant)->remedialLetters;

        return $this->records->attempts($student)
            ->groupBy(fn ($grade): int => $grade->assignment->subject_id)
            ->filter(function (Collection $attempts) use ($remedial): bool {
                $best = $this->records->best($attempts);

                return $best !== null && (float) $best->points > 0 && ! in_array($best->letter, $remedial, true);
            })
            ->keys();
    }

    /**
     * KRS mahasiswa pada semester itu; dibuat sebagai draf berisi paket kelasnya bila belum ada.
     */
    public function start(Student $student, AcademicTerm $term): Enrollment
    {
        $enrollment = Enrollment::query()->where('academic_term_id', $term->id)->where('student_id', $student->id)->first();

        if ($enrollment) {
            return $enrollment;
        }

        $enrollment = Enrollment::create([
            'tenant_id' => $student->tenant_id, 'academic_term_id' => $term->id, 'student_id' => $student->id, 'status' => EnrollmentStatus::Draft,
        ]);

        $groupId = $this->groupFor($student, $term)?->id;
        $package = $this->offerings($student, $term)->where('study_group_id', $groupId)->pluck('id');
        $enrollment->assignments()->sync($package->all());

        return $enrollment;
    }

    /**
     * Simpan pilihan mata kuliah. Gagal bila di luar masa KRS, melebihi SKS maksimal, atau jadwalnya bentrok.
     *
     * @param  array<int, int|string>  $assignmentIds
     */
    public function saveSelection(Enrollment $enrollment, array $assignmentIds): void
    {
        $this->guardEditable($enrollment);

        $student = $enrollment->student;
        $term = $enrollment->academicTerm;

        $chosen = $this->offerings($student, $term)->whereIn('id', array_map('intval', $assignmentIds))->values();
        $this->guardSelection($student, $term, $chosen, $enrollment);

        $enrollment->assignments()->sync($chosen->pluck('id')->all());
    }

    public function submit(Enrollment $enrollment): void
    {
        $this->guardEditable($enrollment);

        $chosen = $enrollment->assignments()->with(['subject.prerequisites', 'studyGroup', 'schedules.timeSlot'])->get();

        if ($chosen->isEmpty()) {
            throw ValidationException::withMessages(['selection' => 'Pilih minimal satu mata kuliah sebelum mengajukan KRS.']);
        }

        $this->guardSelection($enrollment->student, $enrollment->academicTerm, $chosen, $enrollment);

        $enrollment->update(['status' => EnrollmentStatus::Submitted, 'submitted_at' => now(), 'advisor_note' => null, 'decided_by' => null, 'decided_at' => null]);
        $this->audit->log('enrollment.submitted', $enrollment, [], ['credits' => $chosen->sum(fn ($a): int => (int) $a->subject->credits)]);
    }

    /**
     * Tarik kembali pengajuan yang belum diputuskan agar bisa diubah lagi.
     */
    public function withdraw(Enrollment $enrollment): void
    {
        if ($enrollment->status !== EnrollmentStatus::Submitted) {
            throw ValidationException::withMessages(['selection' => 'Hanya pengajuan yang belum diputuskan yang bisa ditarik.']);
        }

        $enrollment->update(['status' => EnrollmentStatus::Draft, 'submitted_at' => null]);
    }

    /**
     * Dosen wali kelas mahasiswa, atau admin, Kaprodi, dan wakil dekan akademik.
     */
    public function canDecide(User $user, Enrollment $enrollment): bool
    {
        if ($user->hasRole(Role::Admin, Role::Principal, Role::Curriculum)) {
            return true;
        }

        if (! $user->hasRole(Role::Teacher) || $user->teacher === null) {
            return false;
        }

        return $this->groupFor($enrollment->student, $enrollment->academicTerm)?->homeroom_teacher_id === $user->teacher->id;
    }

    public function decide(Enrollment $enrollment, User $decider, bool $approve, ?string $note = null): void
    {
        if (! $this->canDecide($decider, $enrollment)) {
            abort(403);
        }

        if ($enrollment->status !== EnrollmentStatus::Submitted) {
            throw ValidationException::withMessages(['decision' => 'KRS ini tidak sedang menunggu persetujuan.']);
        }

        if (! $approve && blank($note)) {
            throw ValidationException::withMessages(['decision' => 'Alasan penolakan wajib diisi.']);
        }

        DB::transaction(function () use ($enrollment, $decider, $approve, $note): void {
            $enrollment->update([
                'status' => $approve ? EnrollmentStatus::Approved : EnrollmentStatus::Rejected,
                'decided_by' => $decider->id, 'decided_at' => now(), 'advisor_note' => filled($note) ? trim((string) $note) : null,
            ]);

            $this->audit->log($approve ? 'enrollment.approved' : 'enrollment.rejected', $enrollment, [], ['note' => $note]);
        });
    }

    /**
     * Bentrok jadwal antar mata kuliah terpilih: hari sama dan jam beririsan.
     *
     * @param  Collection<int, TeachingAssignment>  $assignments
     * @return array<int, string> pesan per pasangan yang bentrok
     */
    public function conflicts(Collection $assignments): array
    {
        $messages = [];
        $slots = $assignments->flatMap(fn (TeachingAssignment $assignment) => $assignment->schedules->map(fn ($schedule) => [$assignment, $schedule->timeSlot]))->values();

        for ($i = 0; $i < $slots->count(); $i++) {
            for ($j = $i + 1; $j < $slots->count(); $j++) {
                [$a, $slotA] = $slots[$i];
                [$b, $slotB] = $slots[$j];

                if ($a->id === $b->id || $slotA->day !== $slotB->day) {
                    continue;
                }

                if ($slotA->starts_at < $slotB->ends_at && $slotB->starts_at < $slotA->ends_at) {
                    $messages["{$a->id}-{$b->id}"] = "{$a->subject->name} bentrok dengan {$b->subject->name} pada {$slotA->dayName()} {$slotA->timeRange()}.";
                }
            }
        }

        return array_values($messages);
    }

    private function guardEditable(Enrollment $enrollment): void
    {
        if ($enrollment->student->status !== StudentStatus::Active) {
            throw ValidationException::withMessages(['selection' => "Status akademikmu {$enrollment->student->status->label()}, jadi KRS tidak bisa diisi. Ajukan aktif kembali di menu Status akademik."]);
        }

        if (! $enrollment->status->isEditable()) {
            throw ValidationException::withMessages(['selection' => 'KRS yang sedang diajukan atau sudah disetujui tidak bisa diubah. Tarik pengajuan dulu bila perlu.']);
        }

        if (! $this->isWindowOpen($enrollment->academicTerm)) {
            throw ValidationException::withMessages(['selection' => 'Masa pengisian KRS sedang ditutup. Lihat jadwalnya di Kalender akademik.']);
        }
    }

    /**
     * @param  Collection<int, TeachingAssignment>  $chosen
     */
    private function guardSelection(Student $student, AcademicTerm $term, Collection $chosen, ?Enrollment $enrollment = null): void
    {
        $credits = $chosen->sum(fn (TeachingAssignment $assignment): int => (int) $assignment->subject->credits);
        $max = $this->maxCredits($student, $term);

        if ($credits > $max) {
            throw ValidationException::withMessages(['selection' => "Total {$credits} SKS melebihi batas {$max} SKS untukmu semester ini."]);
        }

        if ($chosen->pluck('subject_id')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['selection' => 'Satu mata kuliah hanya boleh dipilih di satu kelas.']);
        }

        $passed = $this->curricula->passedForPrerequisites($student);

        foreach ($chosen as $assignment) {
            $unmet = $this->curricula->unmetPrerequisites($assignment->subject, $passed);

            if ($unmet->isNotEmpty()) {
                throw ValidationException::withMessages(['selection' => "{$assignment->subject->name} butuh prasyarat yang belum lulus: ".$unmet->pluck('name')->implode(', ').'.']);
            }
        }

        $seats = $this->seatsTaken($chosen->pluck('id')->all(), $enrollment?->id);

        foreach ($chosen as $assignment) {
            if ($assignment->capacity !== null && ($seats[$assignment->id] ?? 0) >= $assignment->capacity) {
                throw ValidationException::withMessages(['selection' => "Kelas {$assignment->subject->name} ({$assignment->classLabel()}) sudah penuh. Pilih seksi lain."]);
            }
        }

        if ($conflicts = $this->conflicts($chosen)) {
            throw ValidationException::withMessages(['selection' => implode(' ', $conflicts)]);
        }
    }
}
