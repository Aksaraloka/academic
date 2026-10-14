<?php

namespace App\Services;

use App\Enums\ConversionSource;
use App\Enums\ConversionStatus;
use App\Enums\InternshipStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\CreditConversion;
use App\Models\CreditConversionItem;
use App\Models\Internship;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Support\GradeScale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Konversi SKS: pengajuan mata kuliah pengganti dari magang/MBKM, sertifikasi, atau kuliah di kampus lain.
 * Staf menilai tiap mata kuliah; yang disetujui masuk transkrip lewat AcademicRecord.
 */
class CreditConversionService
{
    /** Huruf yang boleh diberikan lewat konversi (E tidak dikonversi). */
    public const LETTERS = ['A', 'B', 'C', 'D'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function isManager(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Principal, Role::Curriculum);
    }

    /**
     * Mata kuliah prodi mahasiswa yang masih bisa diajukan: belum bernilai terkunci dan belum ada di konversi lain.
     *
     * @return Collection<int, Subject>
     */
    public function availableSubjects(Student $student): Collection
    {
        $taken = $this->takenSubjectIds($student);

        return Subject::query()->where('program_id', $student->program_id)->orderBy('semester')->orderBy('name')->get()
            ->reject(fn (Subject $subject): bool => $taken->contains($subject->id))->values();
    }

    /**
     * Magang atau MBKM yang sudah selesai dan belum dipakai pengajuan konversi aktif.
     *
     * @return Collection<int, Internship>
     */
    public function convertibleInternships(Student $student): Collection
    {
        $used = CreditConversion::query()->where('student_id', $student->id)->whereNotNull('internship_id')
            ->whereIn('status', [ConversionStatus::Submitted, ConversionStatus::Approved])->pluck('internship_id');

        return Internship::query()->where('student_id', $student->id)->where('status', InternshipStatus::Completed)
            ->whereNotIn('id', $used)->orderByDesc('ends_on')->get();
    }

    /**
     * @param  array<int, int|string>  $subjectIds
     */
    public function propose(Student $student, ConversionSource $source, ?int $internshipId, string $description, array $subjectIds): CreditConversion
    {
        if ($student->status !== StudentStatus::Active) {
            throw ValidationException::withMessages(['description' => 'Hanya mahasiswa berstatus aktif yang bisa mengajukan konversi SKS.']);
        }

        $internship = null;

        if ($source === ConversionSource::Internship) {
            $internship = $this->convertibleInternships($student)->firstWhere('id', $internshipId);

            if ($internship === null) {
                throw ValidationException::withMessages(['internshipId' => 'Pilih magang atau MBKM yang sudah selesai dan belum dikonversi.']);
            }
        }

        $subjects = $this->availableSubjects($student)->whereIn('id', array_map('intval', $subjectIds))->values();

        if ($subjects->isEmpty() || $subjects->count() !== count(array_unique($subjectIds))) {
            throw ValidationException::withMessages(['subjectIds' => 'Pilih mata kuliah yang masih tersedia untuk dikonversi.']);
        }

        $max = (int) $student->tenant->setting('conversion_max_credits');

        if ($subjects->sum('credits') > $max) {
            throw ValidationException::withMessages(['subjectIds' => "Total SKS yang diajukan {$subjects->sum('credits')}, melebihi batas {$max} SKS per pengajuan."]);
        }

        $conversion = DB::transaction(function () use ($student, $source, $internship, $description, $subjects): CreditConversion {
            $conversion = CreditConversion::create([
                'tenant_id' => $student->tenant_id, 'student_id' => $student->id, 'internship_id' => $internship?->id,
                'source' => $source, 'description' => trim($description), 'status' => ConversionStatus::Submitted,
            ]);

            foreach ($subjects as $subject) {
                $conversion->items()->create(['subject_id' => $subject->id, 'credits' => $subject->credits]);
            }

            return $conversion;
        });
        $this->audit->log('conversion.submitted', $conversion, [], ['credits' => $subjects->sum('credits')]);

        return $conversion;
    }

    /**
     * Setujui dengan huruf mutu tiap mata kuliah.
     *
     * @param  array<int, string>  $letters  id item => huruf
     */
    public function approve(CreditConversion $conversion, User $actor, array $letters, ?string $note): void
    {
        abort_unless($this->isManager($actor), 403);
        $this->ensureSubmitted($conversion);

        $conversion->load('items.subject', 'student');
        $taken = $this->takenSubjectIds($conversion->student, $conversion->id);

        foreach ($conversion->items as $item) {
            $letter = $letters[$item->id] ?? null;

            if (! in_array($letter, self::LETTERS, true)) {
                throw ValidationException::withMessages(['decision' => "Pilih huruf mutu (A–D) untuk {$item->subject->name}."]);
            }

            if ($taken->contains($item->subject_id)) {
                throw ValidationException::withMessages(['decision' => "{$item->subject->name} sudah punya nilai atau konversi lain."]);
            }
        }

        DB::transaction(function () use ($conversion, $actor, $letters, $note): void {
            foreach ($conversion->items as $item) {
                $item->update(['letter' => $letters[$item->id], 'points' => GradeScale::pointsFor($letters[$item->id])]);
            }

            $conversion->update(['status' => ConversionStatus::Approved, 'review_note' => filled($note) ? trim($note) : null, 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
        });
        $this->audit->log('conversion.approved', $conversion, [], ['credits' => $conversion->totalCredits()]);
    }

    public function reject(CreditConversion $conversion, User $actor, string $note): void
    {
        abort_unless($this->isManager($actor), 403);
        $this->ensureSubmitted($conversion);

        if (blank($note)) {
            throw ValidationException::withMessages(['decision' => 'Alasan penolakan wajib diisi.']);
        }

        $conversion->update(['status' => ConversionStatus::Rejected, 'review_note' => trim($note), 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
        $this->audit->log('conversion.rejected', $conversion, [], ['note' => $note]);
    }

    public function cancel(CreditConversion $conversion, User $actor): void
    {
        abort_unless($actor->student !== null && $actor->student->id === $conversion->student_id, 403);
        $this->ensureSubmitted($conversion);

        $conversion->update(['status' => ConversionStatus::Cancelled]);
        $this->audit->log('conversion.cancelled', $conversion);
    }

    /**
     * Id mata kuliah yang tidak bisa dikonversi lagi: sudah bernilai terkunci, atau ada di konversi lain yang menunggu/disetujui.
     *
     * @return Collection<int, int>
     */
    private function takenSubjectIds(Student $student, ?int $exceptConversionId = null): Collection
    {
        $graded = app(AcademicRecord::class)->attempts($student)->map(fn ($grade): int => $grade->assignment->subject_id);

        $converted = CreditConversionItem::query()
            ->whereHas('conversion', fn ($query) => $query->where('student_id', $student->id)
                ->whereIn('status', [ConversionStatus::Submitted, ConversionStatus::Approved])
                ->when($exceptConversionId, fn ($inner) => $inner->whereKeyNot($exceptConversionId)))
            ->pluck('subject_id');

        return $graded->merge($converted)->unique()->values();
    }

    private function ensureSubmitted(CreditConversion $conversion): void
    {
        if ($conversion->fresh()->status !== ConversionStatus::Submitted) {
            throw ValidationException::withMessages(['decision' => 'Pengajuan ini tidak sedang menunggu penilaian.']);
        }
    }
}
