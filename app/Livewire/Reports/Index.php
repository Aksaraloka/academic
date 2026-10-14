<?php

namespace App\Livewire\Reports;

use App\Enums\Role;
use App\Models\AcademicTerm;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\Subject;
use App\Services\AttendanceReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Rekap kehadiran')]
class Index extends Component
{
    #[Url(as: 'group')]
    public string $groupId = '';

    #[Url(as: 'subject')]
    public string $subjectId = '';

    /** Kosong berarti satu semester penuh; isi "YYYY-MM" untuk satu bulan. */
    #[Url(as: 'month')]
    public string $month = '';

    /**
     * Kelas yang boleh dilihat pengguna: semua untuk pimpinan/admin, kelas perwalian dan kelas ajar untuk dosen.
     *
     * @return Collection<int, StudyGroup>
     */
    private function visibleGroups(): Collection
    {
        return StudyGroup::query()
            ->visibleTo(auth()->user())
            ->whereHas('academicTerm', fn ($inner) => $inner->where('is_active', true))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function period(): array
    {
        if ($this->month !== '' && preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $from = CarbonImmutable::createFromFormat('Y-m', $this->month)->startOfMonth();

            return [$from, $from->endOfMonth()];
        }

        $term = AcademicTerm::current();

        return [
            CarbonImmutable::parse($term?->starts_at ?? now()->startOfYear()),
            CarbonImmutable::parse($term?->ends_at ?? now()->endOfYear()),
        ];
    }

    public function render(AttendanceReport $report)
    {
        $user = auth()->user();
        $data = [];

        if ($user->hasRole(Role::Student)) {
            $data['mine'] = $user->student ? [$this->studentRow($report, $user->student)] : [];
        } elseif ($user->hasRole(Role::Guardian)) {
            $data['mine'] = $user->guardian
                ? $user->guardian->students->map(fn ($child) => $this->studentRow($report, $child))->all()
                : [];
        } else {
            $groups = $this->visibleGroups();
            $group = $groups->firstWhere('id', (int) $this->groupId) ?? $groups->first();
            [$from, $to] = $this->period();
            $tenant = $user->tenant;
            $subjectId = $this->subjectId !== '' ? (int) $this->subjectId : null;

            $data += [
                'groups' => $groups,
                'group' => $group,
                'subjects' => $group ? Subject::query()
                    ->whereIn('id', $group->teachingAssignments()->select('subject_id'))
                    ->orderBy('name')->get() : collect(),
                'recap' => $group ? $report->groupRecap($group, $from, $to, $subjectId) : collect(),
                'repeated' => $group ? $report->repeatedAbsences($group, $from, $to, (int) $tenant->setting('absent_threshold'), $subjectId) : collect(),
                'threshold' => (int) $tenant->setting('absent_threshold'),
                'minPercent' => (int) $tenant->setting('min_attendance_percent'),
            ];
        }

        $data['minPercent'] ??= (int) $user->tenant->setting('min_attendance_percent');

        return view('livewire.reports.index', $data);
    }

    /**
     * @return array{student: Student, summary: array<string, mixed>, subjects: Collection<int, array<string, mixed>>}
     */
    private function studentRow(AttendanceReport $report, $student): array
    {
        return [
            'student' => $student,
            'summary' => $report->studentSummary($student->id),
            'subjects' => $report->studentBySubject($student->id),
        ];
    }
}
