<?php

namespace App\Livewire\Programs;

use App\Models\Faculty;
use App\Models\Program;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Fakultas dan prodi')]
class Index extends Component
{
    public string $facultyName = '';

    public string $facultyId = '';

    public string $programName = '';

    public string $programCode = '';

    public string $degree = 'S1';

    public string $programAccreditation = '';

    /** @var array<int, string> akreditasi yang sedang diedit per id prodi */
    public array $accreditations = [];

    public function mount(): void
    {
        $this->accreditations = Program::query()->pluck('accreditation', 'id')->map(fn (?string $value): string => (string) $value)->all();
    }

    public function addFaculty(): void
    {
        $data = $this->validate(['facultyName' => ['required', 'string', 'max:150']], [], ['facultyName' => 'nama fakultas']);

        Faculty::create(['name' => $data['facultyName']]);

        $this->reset('facultyName');
        $this->dispatch('toast', message: 'Fakultas ditambahkan.', type: 'success');
    }

    public function addProgram(): void
    {
        $data = $this->validate([
            'facultyId' => ['required', 'exists:faculties,id'],
            'programName' => ['required', 'string', 'max:150'],
            'programCode' => ['nullable', 'string', 'max:20'],
            'degree' => ['required', 'in:D3,D4,S1,S2,S3'],
            'programAccreditation' => ['nullable', 'string', 'max:30'],
        ], [], ['facultyId' => 'fakultas', 'programName' => 'nama prodi', 'programCode' => 'kode prodi', 'degree' => 'jenjang', 'programAccreditation' => 'akreditasi']);

        $program = Program::create([
            'faculty_id' => $data['facultyId'],
            'name' => $data['programName'],
            'code' => $data['programCode'] ?: null,
            'degree' => $data['degree'],
            'accreditation' => trim((string) $data['programAccreditation']) ?: null,
        ]);

        $this->accreditations[$program->id] = (string) $program->accreditation;
        $this->reset(['programName', 'programCode', 'programAccreditation']);
        $this->dispatch('toast', message: 'Program studi ditambahkan.', type: 'success');
    }

    public function saveAccreditation(int $programId): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $this->validate(["accreditations.{$programId}" => ['nullable', 'string', 'max:30']], [], ["accreditations.{$programId}" => 'akreditasi']);

        // Per model agar pengamat model menerbitkan program.updated ke Admission.
        Program::query()->findOrFail($programId)->update(['accreditation' => trim((string) ($this->accreditations[$programId] ?? '')) ?: null]);

        $this->dispatch('toast', message: 'Akreditasi disimpan.', type: 'success');
    }

    public function render()
    {
        return view('livewire.programs.index', [
            'faculties' => Faculty::query()->with('programs')->orderBy('name')->get(),
        ]);
    }
}
