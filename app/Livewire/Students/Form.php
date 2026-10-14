<?php

namespace App\Livewire\Students;

use App\Enums\StudentStatus;
use App\Models\Guardian;
use App\Models\Program;
use App\Models\Student;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
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

    public string $birth_place = '';

    public string $birth_date = '';

    public string $gender = '';

    public string $religion = '';

    public string $nik = '';

    public string $nationality = 'Indonesia';

    public string $address = '';

    public string $city = '';

    public string $province = '';

    public string $postal_code = '';

    public bool $is_domicile_same = true;

    public string $residence_type = '';

    public string $domicile_address = '';

    public string $domicile_city = '';

    public string $domicile_province = '';

    public string $domicile_postal_code = '';

    public string $phone = '';

    public string $status = 'aktif';

    public string $entry_date = '';

    public string $previous_school = '';

    public string $admission_path = '';

    public string $legacy_credits = '0';

    public string $legacy_quality_points = '0';

    /** @var array<int, array{id: ?int, relation: string, name: string, occupation: string, education: string, phone: string, address: string}> */
    public array $parents = [];

    public function mount(?Student $student = null): void
    {
        $this->parents = [$this->blankParent('Ayah'), $this->blankParent('Ibu')];

        if (! $student?->exists) {
            $this->student = null;
            $this->entry_date = today()->toDateString();
            $this->batch_year = (string) today()->year;

            return;
        }

        $this->student = $student;
        $this->fill([
            'name' => $student->name,
            'nim' => (string) $student->nim,
            'program_id' => (string) $student->program_id,
            'batch_year' => (string) $student->batch_year,
            'birth_place' => (string) $student->birth_place,
            'birth_date' => $student->birth_date?->toDateString() ?? '',
            'gender' => (string) $student->gender,
            'religion' => (string) $student->religion,
            'nik' => (string) $student->nik,
            'nationality' => (string) ($student->nationality ?? 'Indonesia'),
            'address' => (string) $student->address,
            'city' => (string) $student->city,
            'province' => (string) $student->province,
            'postal_code' => (string) $student->postal_code,
            'is_domicile_same' => $student->is_domicile_same,
            'residence_type' => (string) $student->residence_type,
            'domicile_address' => (string) $student->domicile_address,
            'domicile_city' => (string) $student->domicile_city,
            'domicile_province' => (string) $student->domicile_province,
            'domicile_postal_code' => (string) $student->domicile_postal_code,
            'phone' => (string) $student->phone,
            'status' => $student->status->value,
            'entry_date' => $student->entry_date?->toDateString() ?? '',
            'previous_school' => (string) $student->previous_school,
            'admission_path' => (string) $student->admission_path,
            'legacy_credits' => (string) $student->legacy_credits,
            'legacy_quality_points' => (string) (float) $student->legacy_quality_points,
        ]);

        $existing = $student->guardians()->get();
        $rows = collect(['Ayah', 'Ibu'])->map(function (string $relation) use ($existing): array {
            $guardian = $existing->first(fn (Guardian $candidate): bool => $candidate->relation === $relation);

            return $guardian ? $this->parentRow($guardian) : $this->blankParent($relation);
        });

        $others = $existing
            ->reject(fn (Guardian $guardian): bool => in_array($guardian->relation, ['Ayah', 'Ibu'], true))
            ->map(fn (Guardian $guardian): array => $this->parentRow($guardian));

        $this->parents = $rows->concat($others)->values()->all();
    }

    /**
     * @return array{id: ?int, relation: string, name: string, occupation: string, education: string, phone: string, address: string}
     */
    private function blankParent(string $relation): array
    {
        return ['id' => null, 'relation' => $relation, 'name' => '', 'occupation' => '', 'education' => '', 'phone' => '', 'address' => ''];
    }

    /**
     * @return array{id: ?int, relation: string, name: string, occupation: string, education: string, phone: string, address: string}
     */
    private function parentRow(Guardian $guardian): array
    {
        return [
            'id' => $guardian->id,
            'relation' => (string) $guardian->relation,
            'name' => $guardian->name,
            'occupation' => (string) $guardian->occupation,
            'education' => (string) $guardian->education,
            'phone' => (string) $guardian->phone,
            'address' => (string) $guardian->address,
        ];
    }

    public function addGuardian(): void
    {
        $this->parents[] = $this->blankParent('Wali');
    }

    public function removeGuardian(int $index): void
    {
        if (in_array($this->parents[$index]['relation'] ?? null, ['Ayah', 'Ibu'], true)) {
            return;
        }

        unset($this->parents[$index]);
        $this->parents = array_values($this->parents);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'nim' => ['required', 'string', 'max:30', Rule::unique('students', 'nim')->where('tenant_id', $tenantId)->ignore($this->student?->id)],
            'program_id' => ['nullable', 'exists:programs,id'],
            'batch_year' => ['nullable', 'integer', 'between:1990,2100'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['L', 'P'])],
            'religion' => ['nullable', Rule::in(Student::RELIGIONS)],
            'nik' => ['nullable', 'digits:16', Rule::unique('students', 'nik')->where('tenant_id', $tenantId)->ignore($this->student?->id)],
            'nationality' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'digits_between:4,10'],
            'is_domicile_same' => ['boolean'],
            'residence_type' => ['nullable', Rule::in(Student::RESIDENCE_TYPES)],
            'domicile_address' => [Rule::requiredIf(! $this->is_domicile_same), 'nullable', 'string', 'max:500'],
            'domicile_city' => [Rule::requiredIf(! $this->is_domicile_same), 'nullable', 'string', 'max:100'],
            'domicile_province' => ['nullable', 'string', 'max:100'],
            'domicile_postal_code' => ['nullable', 'digits_between:4,10'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::enum(StudentStatus::class)],
            'entry_date' => ['nullable', 'date'],
            'previous_school' => ['nullable', 'string', 'max:255'],
            'admission_path' => ['nullable', Rule::in(Student::ADMISSION_PATHS)],
            'legacy_credits' => ['nullable', 'integer', 'between:0,300'],
            'legacy_quality_points' => ['nullable', 'numeric', 'between:0,1500'],
            'parents' => ['array', 'max:6'],
            'parents.*.id' => ['nullable', 'integer'],
            'parents.*.relation' => ['required', Rule::in(['Ayah', 'Ibu', 'Wali'])],
            'parents.*.name' => ['nullable', 'string', 'max:150'],
            'parents.*.occupation' => ['nullable', 'string', 'max:100'],
            'parents.*.education' => ['nullable', Rule::in(Guardian::EDUCATION_LEVELS)],
            'parents.*.phone' => ['nullable', 'string', 'max:30'],
            'parents.*.address' => ['nullable', 'string', 'max:500'],
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
            'birth_place' => 'tempat lahir',
            'birth_date' => 'tanggal lahir',
            'gender' => 'jenis kelamin',
            'religion' => 'agama',
            'nik' => 'NIK',
            'address' => 'alamat',
            'city' => 'kota/kabupaten',
            'province' => 'provinsi',
            'postal_code' => 'kode pos',
            'residence_type' => 'jenis tempat tinggal',
            'domicile_address' => 'alamat domisili',
            'domicile_city' => 'kota/kabupaten domisili',
            'domicile_province' => 'provinsi domisili',
            'domicile_postal_code' => 'kode pos domisili',
            'phone' => 'telepon',
            'entry_date' => 'tanggal masuk',
            'previous_school' => 'asal sekolah',
            'admission_path' => 'jalur masuk',
            'legacy_credits' => 'SKS nilai awal',
            'legacy_quality_points' => 'total mutu nilai awal',
            'parents.*.name' => 'nama orang tua/wali',
            'parents.*.occupation' => 'pekerjaan',
            'parents.*.education' => 'pendidikan',
            'parents.*.phone' => 'telepon orang tua/wali',
            'parents.*.address' => 'alamat orang tua/wali',
        ];
    }

    public function save(AuditLogger $audit): void
    {
        $validated = $this->validate();
        $parents = $validated['parents'] ?? [];
        unset($validated['parents']);

        $data = collect($validated)->map(fn ($value) => $value === '' ? null : $value)->all();
        $data['nationality'] ??= 'Indonesia';
        $data['legacy_credits'] = (int) ($data['legacy_credits'] ?? 0);
        $data['legacy_quality_points'] = (float) ($data['legacy_quality_points'] ?? 0);

        if ($data['is_domicile_same']) {
            $data = array_merge($data, ['domicile_address' => null, 'domicile_city' => null, 'domicile_province' => null, 'domicile_postal_code' => null]);
        }

        DB::transaction(function () use ($data, $parents, $audit): void {
            if ($this->student) {
                $this->student->update($data);
                $audit->logChanges('student.updated', $this->student);
            } else {
                $this->student = Student::create($data);
                $audit->log('student.created', $this->student, [], $data);
            }

            $this->syncParents($parents);
        });

        session()->flash('toast', 'Data mahasiswa disimpan.');
        $this->redirectRoute('students.index', navigate: true);
    }

    /**
     * Simpan orang tua/wali. Baris dengan nama kosong dilepas dari mahasiswa tanpa menghapus datanya
     * (orang tua yang sama bisa tertaut ke kakak atau adik).
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncParents(array $rows): void
    {
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $existing = ! empty($row['id']) ? $this->student->guardians()->find($row['id']) : null;

            if ($name === '') {
                if ($existing) {
                    $this->student->guardians()->detach($existing->id);
                }

                continue;
            }

            $attributes = [
                'name' => $name,
                'relation' => $row['relation'],
                'occupation' => ($row['occupation'] ?? '') ?: null,
                'education' => ($row['education'] ?? '') ?: null,
                'phone' => ($row['phone'] ?? '') ?: null,
                'address' => ($row['address'] ?? '') ?: null,
            ];

            if ($existing) {
                $existing->update($attributes);
            } else {
                $this->student->guardians()->attach(Guardian::create($attributes)->id);
            }
        }
    }

    public function render()
    {
        return view('livewire.students.form', [
            'programs' => Program::query()->orderBy('name')->get(),
            'religions' => Student::RELIGIONS,
            'residenceTypes' => Student::RESIDENCE_TYPES,
            'paths' => Student::ADMISSION_PATHS,
            'educations' => Guardian::EDUCATION_LEVELS,
        ]);
    }
}
