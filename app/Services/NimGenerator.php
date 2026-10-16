<?php

namespace App\Services;

use App\Models\Program;
use App\Models\Student;
use App\Models\Tenant;

/**
 * Penetapan NIM mahasiswa baru dari pola kampus (`nim_pattern`, bawaan `{YY}{PRODI}{SEQ:4}`). Token: `{YY}` dan
 * `{YYYY}` (angkatan), `{PRODI}` (kode prodi; bila kosong, id prodi 2 digit), dan tepat satu `{SEQ:n}` (urutan n digit
 * per prodi per angkatan). Pemanggil harus berada di dalam transaksi: baris prodi dikunci (`lockForUpdate`) supaya
 * dua serah terima bersamaan tidak mendapat urutan yang sama.
 */
class NimGenerator
{
    private const TOKEN = '/(\{[^{}]+\})/';

    /**
     * Pesan galat bila pola tidak valid, atau null bila valid.
     */
    public static function patternError(string $pattern): ?string
    {
        $pattern = trim($pattern);
        $sequences = 0;

        foreach (preg_split(self::TOKEN, $pattern, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            if (! str_starts_with($part, '{')) {
                if (! preg_match('/^[A-Za-z0-9.\-\/]+$/', $part)) {
                    return 'Pola NIM hanya boleh memuat huruf, angka, titik, strip, garis miring, dan token.';
                }

                continue;
            }

            if (preg_match('/^\{SEQ:([1-9])\}$/', $part)) {
                $sequences++;
            } elseif (! in_array($part, ['{YY}', '{YYYY}', '{PRODI}'], true)) {
                return "Token {$part} tidak dikenal. Gunakan {YY}, {YYYY}, {PRODI}, dan {SEQ:n}.";
            }
        }

        return $sequences === 1 ? null : 'Pola NIM harus memuat tepat satu {SEQ:n} (n = 1 sampai 9 digit).';
    }

    /**
     * NIM berikutnya untuk prodi dan angkatan. Mengunci baris prodi; panggil di dalam `DB::transaction`.
     */
    public function next(Program $program, int $batchYear, ?Tenant $tenant = null): string
    {
        $locked = Program::query()->withoutGlobalScopes()->whereKey($program->id)->lockForUpdate()->firstOrFail();
        $tenant ??= Tenant::query()->findOrFail($locked->tenant_id);

        $pattern = trim((string) $tenant->setting('nim_pattern'));
        $pattern = self::patternError($pattern) === null ? $pattern : (string) Tenant::DEFAULT_SETTINGS['nim_pattern'];

        $width = (int) preg_match('/\{SEQ:([1-9])\}/', $pattern, $match) === 1 ? (int) $match[1] : 4;
        $regex = $this->regex($pattern, $locked, $batchYear);

        $used = Student::query()->withoutGlobalScopes()
            ->where('tenant_id', $locked->tenant_id)->where('program_id', $locked->id)->where('batch_year', $batchYear)->whereNotNull('nim')
            ->pluck('nim')
            ->map(fn (string $nim): ?int => preg_match($regex, $nim, $found) === 1 ? (int) $found[1] : null)
            ->filter(fn (?int $sequence): bool => $sequence !== null);

        $sequence = (int) $used->max() + 1;

        // Pola tanpa {PRODI} dapat berbenturan antarprodi: lompati NIM yang sudah dipakai kampus.
        while (Student::query()->withoutGlobalScopes()->where('tenant_id', $locked->tenant_id)->where('nim', $this->render($pattern, $locked, $batchYear, $sequence, $width))->exists()) {
            $sequence++;
        }

        return $this->render($pattern, $locked, $batchYear, $sequence, $width);
    }

    private function render(string $pattern, Program $program, int $batchYear, int $sequence, int $width): string
    {
        return strtr($pattern, [
            '{YY}' => substr((string) $batchYear, -2),
            '{YYYY}' => (string) $batchYear,
            '{PRODI}' => $this->programCode($program),
            '{SEQ:'.$width.'}' => str_pad((string) $sequence, $width, '0', STR_PAD_LEFT),
        ]);
    }

    /**
     * Ekspresi reguler yang mencocokkan NIM dengan pola ini (untuk prodi dan angkatan tersebut) dan menangkap urutannya.
     */
    private function regex(string $pattern, Program $program, int $batchYear): string
    {
        $regex = '';

        foreach (preg_split(self::TOKEN, $pattern, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            $regex .= match (true) {
                $part === '{YY}' => preg_quote(substr((string) $batchYear, -2), '/'),
                $part === '{YYYY}' => (string) $batchYear,
                $part === '{PRODI}' => preg_quote($this->programCode($program), '/'),
                (bool) preg_match('/^\{SEQ:([1-9])\}$/', $part, $m) => '(\d{'.$m[1].'})',
                default => preg_quote($part, '/'),
            };
        }

        return '/^'.$regex.'$/';
    }

    private function programCode(Program $program): string
    {
        $code = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $program->code));

        return $code !== '' ? $code : str_pad((string) $program->id, 2, '0', STR_PAD_LEFT);
    }
}
