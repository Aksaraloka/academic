<?php

namespace App\Livewire\Students;

use App\Models\Student;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Siswa')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'cari')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $status = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $students = Student::query()
            ->when($this->search !== '', fn ($query) => $query->where(function ($inner): void {
                $inner->where('name', 'like', "%{$this->search}%")
                    ->orWhere('nis', 'like', "%{$this->search}%")
                    ->orWhere('nisn', 'like', "%{$this->search}%");
            }))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.students.index', ['students' => $students]);
    }
}
