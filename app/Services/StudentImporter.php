<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudyGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StudentImporter
{
    /**
     * @var array<int, string>
     */
    public const COLUMNS = ['nim', 'nama', 'jenis_kelamin', 'tanggal_lahir', 'alamat', 'telepon', 'prodi', 'angkatan', 'kelas'];

    /**
     * Baca CSV, validasi tiap baris (duplikat NIM dalam berkas dan di database), dan kembalikan pratinjau (A-04).
     *
     * @return array{valid: array<int, array<string, mixed>>, errors: array<int, array{row: int, message: string}>}
     */
    public function preview(string $path): array
    {
        $handle = fopen($path, 'r');
        $first = fgets($handle);
        $delimiter = substr_count((string) $first, ';') > substr_count((string) $first, ',') ? ';' : ',';
        rewind($handle);

        $header = array_map(fn ($cell) => strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $cell))), fgetcsv($handle, 0, $delimiter) ?: []);

        $existing = Student::query()->pluck('nim')->filter()->flip();
        $seen = [];
        $valid = [];
        $errors = [];
        $line = 1;

        while (($cells = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;

            if (count(array_filter($cells, fn ($cell) => trim((string) $cell) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($header as $index => $column) {
                $row[$column] = trim((string) ($cells[$index] ?? ''));
            }

            $validator = Validator::make($row, [
                'nim' => ['required', 'max:30'],
                'nama' => ['required', 'max:255'],
                'jenis_kelamin' => ['nullable', 'in:L,P'],
                'tanggal_lahir' => ['nullable', 'date'],
            ], [], ['nim' => 'NIM', 'nama' => 'nama', 'jenis_kelamin' => 'jenis kelamin', 'tanggal_lahir' => 'tanggal lahir']);

            if ($validator->fails()) {
                $errors[] = ['row' => $line, 'message' => $validator->errors()->first()];

                continue;
            }

            if ($existing->has($row['nim'])) {
                $errors[] = ['row' => $line, 'message' => "NIM {$row['nim']} sudah terdaftar."];

                continue;
            }

            if (isset($seen[$row['nim']])) {
                $errors[] = ['row' => $line, 'message' => "NIM {$row['nim']} duplikat dengan baris {$seen[$row['nim']]}."];

                continue;
            }

            $seen[$row['nim']] = $line;
            $valid[] = $row;
        }

        fclose($handle);

        return ['valid' => $valid, 'errors' => $errors];
    }

    /**
     * Simpan baris valid dan tempatkan ke kelas bila kolom `kelas` diisi dan rombelnya ada.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function commit(array $rows): int
    {
        $term = AcademicTerm::current();
        $groups = $term
            ? StudyGroup::query()->where('academic_term_id', $term->id)->get()->keyBy(fn (StudyGroup $group) => mb_strtolower($group->name))
            : collect();

        $programs = Program::query()->get()->keyBy(fn ($program) => mb_strtolower($program->name));

        return DB::transaction(function () use ($rows, $groups, $programs): int {
            foreach ($rows as $row) {
                $student = Student::create([
                    'nim' => $row['nim'],
                    'program_id' => $programs->get(mb_strtolower((string) ($row['prodi'] ?? '')))?->id,
                    'batch_year' => (int) ($row['angkatan'] ?? 0) ?: null,
                    'name' => $row['nama'],
                    'gender' => ($row['jenis_kelamin'] ?? '') ?: null,
                    'birth_date' => ($row['tanggal_lahir'] ?? '') ?: null,
                    'address' => ($row['alamat'] ?? '') ?: null,
                    'phone' => ($row['telepon'] ?? '') ?: null,
                    'status' => 'aktif',
                    'entry_date' => today(),
                ]);

                $group = $groups->get(mb_strtolower((string) ($row['kelas'] ?? '')));

                $group?->members()->create(['student_id' => $student->id, 'joined_at' => today()]);
            }

            return count($rows);
        });
    }

    public static function template(): string
    {
        return implode(',', self::COLUMNS)."\n".'2410001,Contoh Mahasiswa,L,2005-05-20,Jl. Mawar 1,08123456789,Teknik Informatika,2024,TI-2024-A'."\n";
    }
}
