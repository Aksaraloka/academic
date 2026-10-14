<?php

namespace App\Services;

use App\Enums\AcademicEventType;
use App\Enums\Role;
use App\Enums\StatusRequestStatus;
use App\Enums\StatusRequestType;
use App\Enums\StudentStatus;
use App\Models\AcademicTerm;
use App\Models\StatusRequest;
use App\Models\Student;
use App\Models\StudentTermStatus;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Status mahasiswa per semester (aktif, cuti, non-aktif, DO, lulus) beserta riwayatnya,
 * pengajuan cuti akademik, dan pengajuan aktif kembali.
 */
class StudentStatusService
{
    public function __construct(
        private readonly AcademicCalendar $calendar,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Cuti akademik hanya bisa diajukan selama masa pengajuan cuti di kalender akademik.
     */
    public function isLeaveWindowOpen(AcademicTerm $term): bool
    {
        return $this->calendar->isOpen($term, AcademicEventType::Leave);
    }

    /**
     * @return Collection<int, StudentTermStatus>
     */
    public function history(Student $student): Collection
    {
        return StudentTermStatus::query()
            ->with(['academicTerm', 'setter'])
            ->where('student_id', $student->id)
            ->get()
            ->sortByDesc(fn (StudentTermStatus $row) => $row->academicTerm->starts_at)
            ->values();
    }

    public function request(Student $student, StatusRequestType $type, string $reason): StatusRequest
    {
        $term = AcademicTerm::current();

        if ($term === null) {
            throw ValidationException::withMessages(['reason' => 'Belum ada semester aktif.']);
        }

        if (StatusRequest::query()->where('student_id', $student->id)->where('status', StatusRequestStatus::Pending)->exists()) {
            throw ValidationException::withMessages(['reason' => 'Kamu masih punya pengajuan yang menunggu keputusan.']);
        }

        if ($type === StatusRequestType::Leave) {
            if ($student->status !== StudentStatus::Active) {
                throw ValidationException::withMessages(['reason' => 'Cuti akademik hanya bisa diajukan oleh mahasiswa berstatus aktif.']);
            }

            if (! $this->isLeaveWindowOpen($term)) {
                throw ValidationException::withMessages(['reason' => 'Masa pengajuan cuti akademik sedang ditutup. Lihat Kalender akademik.']);
            }
        } elseif (! $student->status->canReactivate()) {
            throw ValidationException::withMessages(['reason' => 'Pengajuan aktif kembali hanya untuk mahasiswa yang sedang cuti atau non-aktif.']);
        }

        $request = StatusRequest::create([
            'tenant_id' => $student->tenant_id, 'student_id' => $student->id, 'academic_term_id' => $term->id,
            'type' => $type, 'reason' => trim($reason), 'status' => StatusRequestStatus::Pending,
        ]);
        $this->audit->log('status_request.created', $request, [], ['type' => $type->value]);

        return $request;
    }

    public function canDecide(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Principal, Role::Curriculum);
    }

    public function decide(StatusRequest $request, User $decider, bool $approve, ?string $note = null): void
    {
        abort_unless($this->canDecide($decider), 403);

        if ($request->status !== StatusRequestStatus::Pending) {
            throw ValidationException::withMessages(['decision' => 'Pengajuan ini sudah diputuskan.']);
        }

        if (! $approve && blank($note)) {
            throw ValidationException::withMessages(['decision' => 'Alasan penolakan wajib diisi.']);
        }

        DB::transaction(function () use ($request, $decider, $approve, $note): void {
            $request->update([
                'status' => $approve ? StatusRequestStatus::Approved : StatusRequestStatus::Rejected,
                'decided_by' => $decider->id, 'decided_at' => now(), 'decision_note' => filled($note) ? trim((string) $note) : null,
            ]);

            if ($approve) {
                $this->setStatus(
                    $request->student, $request->academicTerm,
                    $request->type === StatusRequestType::Leave ? StudentStatus::Leave : StudentStatus::Active,
                    $request->type->label().': '.$request->reason, $decider,
                );
            }

            $this->audit->log($approve ? 'status_request.approved' : 'status_request.rejected', $request, [], ['note' => $note]);
        });
    }

    /**
     * Catat status mahasiswa pada satu semester. Bila semester itu aktif, status mahasiswa ikut berubah.
     */
    public function setStatus(Student $student, AcademicTerm $term, StudentStatus $status, ?string $reason, User $actor): StudentTermStatus
    {
        $row = StudentTermStatus::query()->updateOrCreate(
            ['student_id' => $student->id, 'academic_term_id' => $term->id],
            ['tenant_id' => $student->tenant_id, 'status' => $status, 'reason' => $reason, 'set_by' => $actor->id],
        );

        if ($term->is_active) {
            $before = $student->status;
            $student->update(['status' => $status]);
            $this->audit->log('student.status_changed', $student, ['status' => $before->value], ['status' => $status->value, 'term' => $term->id]);
        }

        return $row;
    }
}
