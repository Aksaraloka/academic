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
        ], [], ['facultyId' => 'fakultas', 'programName' => 'nama prodi', 'programCode' => 'kode prodi', 'degree' => 'jenjang']);

        Program::create([
            'faculty_id' => $data['facultyId'],
            'name' => $data['programName'],
            'code' => $data['programCode'] ?: null,
            'degree' => $data['degree'],
        ]);

        $this->reset(['programName', 'programCode']);
        $this->dispatch('toast', message: 'Program studi ditambahkan.', type: 'success');
    }

    public function render()
    {
        return view('livewire.programs.index', [
            'faculties' => Faculty::query()->with('programs')->orderBy('name')->get(),
        ]);
    }
}
