<?php

namespace App\Services;

use App\Enums\LetterStatus;
use App\Enums\LetterType;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\AcademicTerm;
use App\Models\LetterRequest;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Permohonan surat mahasiswa: pengajuan, persetujuan staf, penomoran, dan data beku (snapshot) yang dicetak ke PDF dengan QR verifikasi.
 */
class LetterService
{
    private const ROMAN_MONTHS = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AcademicRecord $records,
    ) {}

    public function isManager(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Principal, Role::Curriculum);
    }

    /**
     * Ajukan surat. Jenis yang tidak butuh persetujuan langsung terbit.
     *
     * @param  array<string, mixed>  $details
     */
    public function request(Student $student, LetterType $type, string $purpose, array $details = []): LetterRequest
    {
        if ($student->status !== StudentStatus::Active) {
            throw ValidationException::withMessages(['purpose' => 'Hanya mahasiswa berstatus aktif yang bisa mengajukan surat.']);
        }

        $pending = LetterRequest::query()->where('student_id', $student->id)->where('type', $type)->where('status', LetterStatus::Submitted)->exists();

        if ($pending) {
            throw ValidationException::withMessages(['purpose' => 'Pengajuan '.mb_strtolower($type->label()).' sebelumnya masih menunggu persetujuan.']);
        }

        $letter = LetterRequest::create([
            'tenant_id' => $student->tenant_id, 'student_id' => $student->id, 'type' => $type,
            'status' => LetterStatus::Submitted, 'purpose' => trim($purpose), 'details' => $details ?: null,
        ]);
        $this->audit->log('letter.requested', $letter, [], ['type' => $type->value, 'purpose' => $letter->purpose]);

        if (! $type->requiresApproval()) {
            $this->issue($letter, null, null);
        }

        return $letter->refresh();
    }

    public function approve(LetterRequest $letter, User $actor, ?string $note): void
    {
        abort_unless($this->isManager($actor), 403);
        $this->ensureSubmitted($letter);

        $this->issue($letter, $actor, filled($note) ? trim((string) $note) : null);
    }

    public function reject(LetterRequest $letter, User $actor, string $note): void
    {
        abort_unless($this->isManager($actor), 403);
        $this->ensureSubmitted($letter);

        if (blank($note)) {
            throw ValidationException::withMessages(['note' => 'Alasan penolakan wajib diisi.']);
        }

        $letter->update(['status' => LetterStatus::Rejected, 'decision_note' => trim($note), 'decided_by' => $actor->id, 'decided_at' => now()]);
        $this->audit->log('letter.rejected', $letter, [], ['note' => $note]);
    }

    public function cancel(LetterRequest $letter, User $actor): void
    {
        abort_unless($actor->student !== null && $actor->student->id === $letter->student_id, 403);
        $this->ensureSubmitted($letter);

        $letter->update(['status' => LetterStatus::Cancelled]);
        $this->audit->log('letter.cancelled', $letter);
    }

    /**
     * Surat terbit yang cocok dengan kode pada QR; null bila kode tidak dikenal. Tanpa batas kampus karena dipakai publik.
     */
    public function verify(string $code): ?LetterRequest
    {
        return LetterRequest::withoutGlobalScopes()->with('student.program')
            ->where('verification_code', $code)->where('status', LetterStatus::Issued)->first();
    }

    /**
     * Pemilik surat atau staf akademik.
     */
    public function canView(User $user, LetterRequest $letter): bool
    {
        return $this->isManager($user) || ($user->student !== null && $user->student->id === $letter->student_id);
    }

    private function ensureSubmitted(LetterRequest $letter): void
    {
        if ($letter->status !== LetterStatus::Submitted) {
            throw ValidationException::withMessages(['note' => 'Pengajuan ini tidak sedang menunggu keputusan.']);
        }
    }

    private function issue(LetterRequest $letter, ?User $actor, ?string $note): void
    {
        DB::transaction(function () use ($letter, $actor, $note): void {
            $year = now()->year;
            $sequence = 1 + (int) LetterRequest::withoutGlobalScopes()
                ->where('tenant_id', $letter->tenant_id)->where('type', $letter->type)->where('number_year', $year)->max('number_seq');

            $letter->update([
                'status' => LetterStatus::Issued,
                'number' => sprintf('%03d/%s/AKD/%s/%d', $sequence, $letter->type->code(), self::ROMAN_MONTHS[now()->month - 1], $year),
                'number_year' => $year,
                'number_seq' => $sequence,
                'verification_code' => Str::upper(Str::random(12)),
                'snapshot' => $this->snapshot($letter),
                'issued_at' => now(),
                'decision_note' => $note,
                'decided_by' => $actor?->id,
                'decided_at' => $actor ? now() : null,
            ]);
        });

        $this->audit->log('letter.issued', $letter, [], ['number' => $letter->number, 'automatic' => $actor === null]);
    }

    /**
     * Data mahasiswa dan akademik pada saat surat terbit, supaya surat tidak berubah bila data berubah kemudian.
     *
     * @return array<string, mixed>
     */
    private function snapshot(LetterRequest $letter): array
    {
        $student = $letter->student()->with('program.faculty')->first();
        $term = AcademicTerm::withoutGlobalScopes()->where('tenant_id', $student->tenant_id)->where('is_active', true)->first();
        $transcript = $this->records->transcript($student);

        $snapshot = [
            'student' => [
                'name' => $student->name,
                'nim' => $student->nim,
                'birth' => trim(($student->birth_place ?? '').($student->birth_place && $student->birth_date ? ', ' : '').($student->birth_date?->translatedFormat('j F Y') ?? '')),
                'program' => $student->program?->label(),
                'faculty' => $student->program?->faculty?->name,
                'batch_year' => $student->batch_year,
            ],
            'term' => $term?->name,
            'semester' => $this->records->semesterOf($student, $term),
            'credits' => $transcript['credits_passed'],
            'gpa' => $transcript['ipk'],
        ];

        if ($letter->type === LetterType::ProvisionalTranscript) {
            $snapshot['rows'] = $transcript['rows']->map(fn (array $row): array => [
                'term' => $row['term']->name, 'code' => $row['subject']->code, 'name' => $row['subject']->name,
                'credits' => $row['credits'], 'letter' => $row['letter'], 'points' => $row['points'],
            ])->merge($transcript['converted']->map(fn (array $row): array => [
                'term' => 'Konversi', 'code' => $row['subject']->code, 'name' => $row['subject']->name,
                'credits' => $row['credits'], 'letter' => $row['letter'], 'points' => $row['points'],
            ]))->values()->all();
            $snapshot['legacy_credits'] = $transcript['legacy_credits'];
        }

        if ($letter->type === LetterType::Recommendation && ($teacherId = $letter->details['recommender_id'] ?? null)) {
            $snapshot['recommender'] = Teacher::withoutGlobalScopes()->whereKey($teacherId)->value('name');
        }

        return $snapshot;
    }
}
