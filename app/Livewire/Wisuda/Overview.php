<?php

namespace App\Livewire\Wisuda;

use App\Enums\GraduationStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\GraduationPeriod;
use App\Models\GraduationRegistration;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Pendaftaran wisuda')]
class Overview extends Component
{
    #[Url(as: 'periode')]
    public string $periodId = '';

    public function render()
    {
        $user = auth()->user();
        $isStudent = $user->hasRole(Role::Student);

        $periods = GraduationPeriod::query()
            ->when($isStudent, fn ($query) => $query->where('is_published', true))
            ->orderByDesc('ceremony_on')
            ->get();

        $period = $periods->firstWhere('id', (int) $this->periodId)
            ?? $periods->first(fn (GraduationPeriod $candidate): bool => $candidate->phase() === 'open')
            ?? $periods->first();

        $data = ['periods' => $periods, 'period' => $period, 'isStudent' => $isStudent];

        if ($period === null) {
            return view('livewire.wisuda.overview', $data);
        }

        if ($isStudent) {
            $student = $user->student;
            $tenant = $user->tenant;
            $registration = $student
                ? GraduationRegistration::query()->where('graduation_period_id', $period->id)->where('student_id', $student->id)->first()
                : null;

            $data += [
                'student' => $student,
                'registration' => $registration,
                'requirements' => [
                    ['label' => 'Berstatus mahasiswa aktif', 'met' => $student?->status === StudentStatus::Active],
                    ['label' => 'Terdaftar pada program studi', 'met' => $student?->program_id !== null],
                    ['label' => 'SKS lulus minimal '.$tenant->setting('graduation_min_credits'), 'met' => null],
                    ['label' => 'IPK minimal '.number_format((float) $tenant->setting('graduation_min_gpa'), 2, ',', '.'), 'met' => null],
                    ['label' => 'Sudah menyelesaikan sidang akhir', 'met' => null],
                ],
            ];

            return view('livewire.wisuda.overview', $data);
        }

        $registrations = $period->registrations()->with('student.program')->latest()->get();

        $data += [
            'counts' => [
                'total' => $registrations->count(),
                'submitted' => $registrations->where('status', GraduationStatus::Submitted)->count(),
                'approved' => $registrations->where('status', GraduationStatus::Approved)->count(),
                'rejected' => $registrations->where('status', GraduationStatus::Rejected)->count(),
            ],
            'byProgram' => $registrations
                ->where('status', '!=', GraduationStatus::Rejected)
                ->groupBy(fn (GraduationRegistration $registration): string => $registration->student->program?->label() ?? 'Tanpa program studi')
                ->map->count()
                ->sortDesc(),
            'pending' => $registrations->where('status', GraduationStatus::Submitted)->take(5),
            'minute' => $period->minute,
            'canManage' => $user->isAdmin(),
        ];

        return view('livewire.wisuda.overview', $data);
    }
}
