<?php

namespace App\Livewire\Leaves;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\Role;
use App\Models\LeaveRequest;
use App\Models\Student;
use App\Models\StudyGroup;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Izin dan sakit')]
class Index extends Component
{
    use WithFileUploads;

    public bool $showForm = false;

    public string $studentId = '';

    public string $type = 'izin';

    public string $starts_on = '';

    public string $ends_on = '';

    public string $reason = '';

    public $attachment;

    public function mount(): void
    {
        $this->starts_on = $this->ends_on = today()->toDateString();
    }

    /**
     * Mahasiswa yang boleh dicatat izinnya oleh pengguna ini (dosen wali atau admin).
     */
    private function recordableStudents()
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return Student::query()->where('status', 'aktif')->orderBy('name')->get();
        }

        if (! $user->teacher) {
            return collect();
        }

        return Student::query()
            ->whereHas('memberships', fn ($query) => $query->whereNull('left_at')
                ->whereIn('study_group_id', StudyGroup::query()->where('homeroom_teacher_id', $user->teacher->id)->select('id')))
            ->orderBy('name')
            ->get();
    }

    public function save(): void
    {
        $user = auth()->user();
        $forStudent = $this->studentId !== '' || $user->isAdmin() && $this->studentId !== '';
        $isTeacherOwn = ! $forStudent && $user->teacher;

        $rules = [
            'type' => ['required', 'in:izin,sakit,dinas,cuti'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['nullable', 'string', 'max:500'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:3072'],
        ];

        if ($forStudent) {
            $rules['studentId'] = ['required', 'in:'.$this->recordableStudents()->pluck('id')->implode(',')];
            $rules['type'] = ['required', 'in:izin,sakit'];
        }

        $this->validate($rules, [], [
            'type' => 'jenis', 'starts_on' => 'tanggal mulai', 'ends_on' => 'tanggal selesai',
            'reason' => 'alasan', 'attachment' => 'lampiran', 'studentId' => 'mahasiswa',
        ]);

        abort_unless($forStudent || $isTeacherOwn, 403);

        LeaveRequest::create([
            'requester_id' => $user->id,
            'subject_type' => $forStudent ? 'student' : 'teacher',
            'subject_id' => $forStudent ? (int) $this->studentId : $user->teacher->id,
            'type' => $this->type,
            'starts_on' => $this->starts_on,
            'ends_on' => $this->ends_on,
            'reason' => $this->reason ?: null,
            'attachment' => $this->attachment?->store('leave-attachments', 'local'),
            'status' => $forStudent ? LeaveStatus::Approved : LeaveStatus::Pending,
            'approved_by' => $forStudent ? $user->id : null,
        ]);

        $this->reset(['showForm', 'studentId', 'reason', 'attachment']);
        $this->dispatch('toast', message: $forStudent ? 'Izin mahasiswa dicatat.' : 'Pengajuan dikirim.', type: 'success');
    }

    public function decide(int $leaveId, string $decision): void
    {
        abort_unless(auth()->user()->hasRole(Role::Admin, Role::Principal), 403);

        $leave = LeaveRequest::query()->where('subject_type', 'teacher')->findOrFail($leaveId);
        $leave->update([
            'status' => $decision === 'approve' ? LeaveStatus::Approved : LeaveStatus::Rejected,
            'approved_by' => auth()->id(),
        ]);

        $this->dispatch('toast', message: 'Keputusan disimpan.', type: 'success');
    }

    public function render()
    {
        $user = auth()->user();
        $recordable = $this->recordableStudents();

        $leaves = LeaveRequest::query()
            ->with('requester')
            ->when(! $user->hasRole(Role::Admin, Role::Principal), fn ($query) => $query->where('requester_id', $user->id))
            ->latest()
            ->limit(50)
            ->get();

        $names = Student::query()->whereIn('id', $leaves->where('subject_type', 'student')->pluck('subject_id'))->pluck('name', 'id');

        return view('livewire.leaves.index', [
            'leaves' => $leaves,
            'studentNames' => $names,
            'recordable' => $recordable,
            'canApprove' => $user->hasRole(Role::Admin, Role::Principal),
            'canRequestOwn' => $user->teacher !== null,
            'types' => LeaveType::cases(),
        ]);
    }
}
