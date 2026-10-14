<?php

namespace App\Livewire\Graduation;

use App\Enums\GraduationStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\GraduationPeriod;
use App\Models\GraduationRegistration;
use App\Services\GraduationService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Pendaftaran wisuda')]
class Overview extends Component
{
    #[Url(as: 'period')]
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
            return view('livewire.graduation.overview', $data);
        }

        if ($isStudent) {
            $student = $user->student;
            $tenant = $user->tenant;
            $eligibility = $student
                ? app(GraduationService::class)->eligibility($student)
                : [
                    'credits' => 0, 'gpa' => null, 'meets_credits' => false, 'meets_gpa' => false,
                    'min_credits' => (int) $tenant->setting('graduation_min_credits'), 'min_gpa' => (float) $tenant->setting('graduation_min_gpa'),
                ];
            $registration = $student
                ? GraduationRegistration::query()->where('graduation_period_id', $period->id)->where('student_id', $student->id)->first()
                : null;

            $data += [
                'student' => $student,
                'registration' => $registration,
                'requirements' => [
                    ['label' => 'Berstatus mahasiswa aktif', 'met' => $student?->status === StudentStatus::Active],
                    ['label' => 'Terdaftar pada program studi', 'met' => $student?->program_id !== null],
                    ['label' => 'SKS lulus minimal '.$eligibility['min_credits'].' (kamu: '.$eligibility['credits'].')', 'met' => $eligibility['meets_credits']],
                    ['label' => 'IPK minimal '.number_format($eligibility['min_gpa'], 2, ',', '.').' (kamu: '.($eligibility['gpa'] === null ? '–' : number_format($eligibility['gpa'], 2, ',', '.')).')', 'met' => $eligibility['meets_gpa']],
                    ['label' => 'Sudah menyelesaikan sidang akhir', 'met' => null],
                ],
            ];

            return view('livewire.graduation.overview', $data);
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

        return view('livewire.graduation.overview', $data);
    }
}
