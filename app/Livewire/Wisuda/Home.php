<?php

namespace App\Livewire\Wisuda;

use App\Enums\Role;
use App\Models\GraduationNews;
use App\Models\GraduationPeriod;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Beranda wisuda')]
class Home extends Component
{
    use WithPagination;

    public function render()
    {
        $user = auth()->user();
        $isStudent = $user->hasRole(Role::Student);

        $periods = GraduationPeriod::query()
            ->when($isStudent, fn ($query) => $query->where('is_published', true))
            ->orderByDesc('ceremony_on')
            ->get();

        $period = $periods->first(fn (GraduationPeriod $candidate): bool => $candidate->phase() === 'open') ?? $periods->first();

        return view('livewire.wisuda.home', [
            'period' => $period,
            'isStudent' => $isStudent,
            'isAdmin' => $user->isAdmin(),
            'news' => GraduationNews::query()
                ->visibleTo($user)
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate(9),
        ]);
    }
}
