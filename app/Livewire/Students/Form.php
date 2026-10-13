<?php

namespace App\Livewire\Students;

use App\Enums\StudentStatus;
use App\Models\Program;
use App\Models\Student;
use App\Services\AuditLogger;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Data mahasiswa')]
class Form extends Component
{
    public ?Student $student = null;

    public string $name = '';

    public string $nim = '';

    public string $program_id = '';

    public string $batch_year = '';

    public string $birth_date = '';

    public string $gender = '';

    public string $address = '';

    public string $phone = '';

    public string $status = 'aktif';

    public string $entry_date = '';

    public function mount(?Student $student = null): void
    {
        if ($student?->exists) {
            $this->student = $student;
            $this->fill([
                'name' => $student->name,
                'nim' => (string) $student->nim,
                'program_id' => (string) $student->program_id,
                'batch_year' => (string) $student->batch_year,
                'birth_date' => $student->birth_date?->toDateString() ?? '',
                'gender' => (string) $student->gender,
                'address' => (string) $student->address,
                'phone' => (string) $student->phone,
                'status' => $student->status->value,
                'entry_date' => $student->entry_date?->toDateString() ?? '',
            ]);
        } else {
            $this->student = null;
            $this->entry_date = today()->toDateString();
            $this->batch_year = (string) today()->year;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'nim' => ['required', 'string', 'max:30', Rule::unique('students', 'nim')
                ->where('tenant_id', auth()->user()->tenant_id)
                ->ignore($this->student?->id)],
            'program_id' => ['nullable', 'exists:programs,id'],
            'batch_year' => ['nullable', 'integer', 'between:1990,2100'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['L', 'P'])],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::enum(StudentStatus::class)],
            'entry_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'nama',
            'nim' => 'NIM',
            'program_id' => 'program studi',
            'batch_year' => 'angkatan',
            'birth_date' => 'tanggal lahir',
            'gender' => 'jenis kelamin',
            'address' => 'alamat',
            'phone' => 'telepon',
            'entry_date' => 'tanggal masuk',
        ];
    }

    public function save(AuditLogger $audit): void
    {
        $data = collect($this->validate())
            ->map(fn ($value) => $value === '' ? null : $value)
            ->all();

        if ($this->student) {
            $this->student->update($data);
            $audit->logChanges('student.updated', $this->student);
        } else {
            $this->student = Student::create($data);
            $audit->log('student.created', $this->student, [], $data);
        }

        session()->flash('toast', 'Data mahasiswa disimpan.');
        $this->redirectRoute('students.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.students.form', [
            'programs' => Program::query()->orderBy('name')->get(),
        ]);
    }
}
