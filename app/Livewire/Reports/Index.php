<?php

namespace App\Livewire\Reports;

use App\Enums\Role;
use App\Models\StudyGroup;
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

    #[Url(as: 'bulan')]
    public string $month = '';

    public function mount(): void
    {
        $this->month = $this->month !== '' ? $this->month : now()->format('Y-m');
    }

    /**
     * Rombel yang boleh dilihat pengguna: semua untuk pimpinan/admin, kelas binaan dan kelas ajar untuk guru.
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

    public function render(AttendanceReport $report)
    {
        $user = auth()->user();
        $data = ['month' => $this->month];

        if ($user->hasRole(Role::Student)) {
            $data['mine'] = $user->student ? [['student' => $user->student, 'summary' => $report->studentSummary($user->student->id)]] : [];
        } elseif ($user->hasRole(Role::Guardian)) {
            $data['mine'] = $user->guardian
                ? $user->guardian->students->map(fn ($child) => ['student' => $child, 'summary' => $report->studentSummary($child->id)])->all()
                : [];
        } else {
            $groups = $this->visibleGroups();
            $group = $groups->firstWhere('id', (int) $this->groupId) ?? $groups->first();
            $from = CarbonImmutable::createFromFormat('Y-m', $this->month)->startOfMonth();
            $to = $from->endOfMonth();
            $tenant = $user->tenant;

            $data += [
                'groups' => $groups,
                'group' => $group,
                'recap' => $group ? $report->groupRecap($group, $from, $to) : collect(),
                'repeated' => $group ? $report->repeatedAbsences($group, $from, $to, (int) $tenant->setting('absent_threshold')) : collect(),
                'threshold' => (int) $tenant->setting('absent_threshold'),
                'minPercent' => (int) $tenant->setting('min_attendance_percent'),
            ];
        }

        return view('livewire.reports.index', $data);
    }
}
