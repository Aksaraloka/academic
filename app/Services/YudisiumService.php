<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Enums\ThesisStatus;
use App\Models\GraduationDecision;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\Thesis;
use App\Models\User;
use App\Models\Yudisium;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Yudisium: pengecekan syarat lulus otomatis (SKS, IPK, nilai E, batas nilai D, tugas akhir, tunggakan),
 * penetapan predikat kelulusan, dan keputusan kelulusan per mahasiswa.
 */
class YudisiumService
{
    public function __construct(
        private readonly AcademicRecord $records,
        private readonly AuditLogger $audit,
    ) {}

    public function isManager(User $user): bool
    {
        return $user->hasRole(Role::Admin, Role::Principal, Role::Curriculum);
    }

    /**
     * Evaluasi syarat lulus seorang mahasiswa dari transkrip dan data pendukung.
     *
     * @return array{
     *     checks: array<int, array{key: string, label: string, detail: string, met: bool}>, eligible: bool,
     *     credits: int, gpa: ?float, d_percent: float, e_count: int, years: float, predicate: ?string
     * }
     */
    public function evaluate(Student $student, ?CarbonInterface $on = null): array
    {
        $on ??= today();
        $tenant = $student->tenant;
        $transcript = $this->records->transcript($student);

        $credits = $transcript['credits_passed'];
        $gpa = $transcript['ipk'];
        $minCredits = (int) $tenant->setting('graduation_min_credits');
        $minGpa = (float) $tenant->setting('graduation_min_gpa');
        $maxD = (float) $tenant->setting('graduation_max_d_percent');

        $rows = $transcript['rows']->concat($transcript['converted']);
        $eCount = $rows->filter(fn (array $row): bool => ! $row['passed'])->count();
        $dCredits = (int) $rows->where('letter', 'D')->sum('credits');
        $dPercent = $credits > 0 ? round($dCredits / $credits * 100, 1) : 0.0;

        $thesisRequired = (bool) $tenant->setting('thesis_required_for_graduation');
        $thesis = Thesis::query()->where('student_id', $student->id)->where('status', ThesisStatus::Completed)->latest('id')->first();

        $arrears = Invoice::query()->where('student_id', $student->id)->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])
            ->whereDate('due_on', '<', $on->toDateString())->where('krs_exempt', false)->count();

        $checks = [
            ['key' => 'status', 'label' => 'Berstatus mahasiswa aktif', 'detail' => $student->status->label(), 'met' => in_array($student->status, [StudentStatus::Active, StudentStatus::Graduated], true)],
            ['key' => 'credits', 'label' => "SKS lulus minimal {$minCredits}", 'detail' => "{$credits} SKS", 'met' => $credits >= $minCredits],
            ['key' => 'gpa', 'label' => 'IPK minimal '.number_format($minGpa, 2, ',', '.'), 'detail' => $gpa === null ? 'Belum ada nilai' : number_format($gpa, 2, ',', '.'), 'met' => $gpa !== null && $gpa >= $minGpa],
            ['key' => 'no_e', 'label' => 'Tidak ada mata kuliah dengan nilai E', 'detail' => $eCount === 0 ? 'Tidak ada' : "{$eCount} mata kuliah", 'met' => $eCount === 0],
            ['key' => 'max_d', 'label' => "Nilai D maksimal {$maxD}% dari SKS lulus", 'detail' => number_format($dPercent, 1, ',', '.').'% ('.$dCredits.' SKS)', 'met' => $dPercent <= $maxD],
        ];

        if ($thesisRequired) {
            $checks[] = ['key' => 'thesis', 'label' => 'Tugas akhir lulus sidang', 'detail' => $thesis ? 'Lulus ('.($thesis->final_letter ?? '-').')' : 'Belum', 'met' => $thesis !== null];
        }

        $checks[] = ['key' => 'arrears', 'label' => 'Tidak ada tunggakan pembayaran', 'detail' => $arrears === 0 ? 'Tidak ada' : "{$arrears} tagihan lewat jatuh tempo", 'met' => $arrears === 0];

        $eligible = collect($checks)->every(fn (array $check): bool => $check['met']);
        $years = round(($student->entry_date ?? now()->setDate((int) $student->batch_year, 9, 1))->floatDiffInYears($on), 1);

        return [
            'checks' => $checks, 'eligible' => $eligible, 'credits' => $credits, 'gpa' => $gpa,
            'd_percent' => $dPercent, 'e_count' => $eCount, 'years' => $years,
            'predicate' => $eligible ? $this->predicate((float) $gpa, $dCredits, $years, $student) : null,
        ];
    }

    /**
     * Predikat: Dengan Pujian (IPK tinggi, tanpa nilai D, dan masa studi dalam batas), Sangat Memuaskan, atau Memuaskan.
     */
    public function predicate(float $gpa, int $dCredits, float $years, Student $student): string
    {
        $tenant = $student->tenant;

        if ($gpa >= (float) $tenant->setting('predicate_cum_laude_gpa') && $dCredits === 0 && $years <= (float) $tenant->setting('predicate_cum_laude_max_years')) {
            return 'Dengan Pujian';
        }

        return $gpa >= (float) $tenant->setting('predicate_very_satisfactory_gpa') ? 'Sangat Memuaskan' : 'Memuaskan';
    }

    /**
     * Calon yudisium: mahasiswa aktif yang tugas akhirnya sudah lulus sidang (atau, bila kampus tidak mewajibkannya,
     * angkatan yang sudah menempuh lebih dari tiga tahun), dan belum punya keputusan kelulusan.
     *
     * @return Collection<int, Student>
     */
    public function candidates(Tenant $tenant): Collection
    {
        $decided = GraduationDecision::query()->pluck('student_id');

        return Student::query()->with('program')
            ->where('status', StudentStatus::Active)->whereNotIn('id', $decided)
            ->when(
                (bool) $tenant->setting('thesis_required_for_graduation'),
                fn ($query) => $query->whereIn('id', Thesis::query()->where('status', ThesisStatus::Completed)->select('student_id')),
                fn ($query) => $query->where('batch_year', '<=', now()->year - 3),
            )
            ->orderBy('name')->get();
    }

    public function decisionFor(Student $student): ?GraduationDecision
    {
        return GraduationDecision::query()->with('yudisium')->where('student_id', $student->id)->latest('id')->first();
    }

    /**
     * Tetapkan kelulusan untuk mahasiswa yang memenuhi syarat: status menjadi Lulus, tanggal dan predikat dicatat.
     *
     * @param  array<int, int>  $studentIds
     * @return int jumlah mahasiswa yang diluluskan
     */
    public function finalize(Yudisium $yudisium, User $actor, array $studentIds, ?string $number): int
    {
        abort_unless($this->isManager($actor), 403);

        if ($yudisium->isFinalized()) {
            throw ValidationException::withMessages(['selected' => 'Yudisium ini sudah ditetapkan.']);
        }

        $students = Student::query()->with('program')->whereIn('id', $studentIds)->where('status', StudentStatus::Active)->get();
        $granted = 0;

        DB::transaction(function () use ($students, $yudisium, $actor, $number, &$granted): void {
            foreach ($students as $student) {
                $result = $this->evaluate($student, $yudisium->held_on);

                if (! $result['eligible']) {
                    continue;
                }

                GraduationDecision::create([
                    'tenant_id' => $student->tenant_id, 'yudisium_id' => $yudisium->id, 'student_id' => $student->id,
                    'credits' => $result['credits'], 'gpa' => $result['gpa'], 'predicate' => $result['predicate'], 'checks' => $result['checks'], 'decided_by' => $actor->id,
                ]);
                $student->update(['status' => StudentStatus::Graduated, 'graduated_on' => $yudisium->held_on, 'graduation_predicate' => $result['predicate']]);
                $granted++;
            }

            if ($granted === 0) {
                throw ValidationException::withMessages(['selected' => 'Tidak ada mahasiswa terpilih yang memenuhi semua syarat.']);
            }

            $yudisium->update(['status' => 'finalized', 'finalized_at' => now(), 'finalized_by' => $actor->id, 'number' => $number ?: $yudisium->number]);
        });

        $this->audit->log('yudisium.finalized', $yudisium, [], ['graduated' => $granted]);

        return $granted;
    }
}
