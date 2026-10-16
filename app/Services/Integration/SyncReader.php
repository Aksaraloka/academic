<?php

namespace App\Services\Integration;

use App\Models\AcademicTerm;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\Tenant;
use App\Services\AttendanceReport;
use App\Services\ClassRoster;
use App\Services\LmsIntegration;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Pembaca data induk untuk LMS. Request token klien server tidak punya pengguna login sehingga `TenantScope` tidak
 * aktif; semua query di sini dibatasi eksplisit ke tenant integrasi (`integration.lms.tenant_id`), dan data tenant
 * lain diperlakukan seperti tidak ada (404).
 */
class SyncReader
{
    public function __construct(
        private readonly LmsIntegration $lms,
        private readonly ClassRoster $roster,
        private readonly AttendanceReport $attendance,
    ) {}

    /**
     * @throws HttpException 403 bila integrasi belum disiapkan
     */
    public function tenantId(): int
    {
        $tenantId = $this->lms->tenantId();

        abort_if($tenantId === null, 403, 'Integrasi Kelas Online belum disiapkan.');

        return $tenantId;
    }

    /**
     * @return Builder<AcademicTerm>
     */
    public function terms(): Builder
    {
        return AcademicTerm::query()->withoutGlobalScopes()->where('tenant_id', $this->tenantId())->orderByDesc('starts_at')->orderBy('id');
    }

    public function findTerm(int $id): AcademicTerm
    {
        return $this->terms()->findOrFail($id);
    }

    /**
     * @return Builder<TeachingAssignment>
     */
    public function classes(AcademicTerm $term): Builder
    {
        return $this->assignments()->where('academic_term_id', $term->id)->with(['subject', 'teacher.user', 'studyGroup'])->orderBy('id');
    }

    public function findClass(int $id): TeachingAssignment
    {
        return $this->assignments()->with(['academicTerm', 'subject', 'teacher.user', 'studyGroup'])->findOrFail($id);
    }

    /**
     * Peserta kelas yang punya akun, dari `ClassRoster` (anggota kelas angkatan, KRS disetujui, pindah seksi).
     *
     * @return Builder<Student>
     */
    public function rosterStudents(TeachingAssignment $class): Builder
    {
        return Student::query()->withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId())
            ->whereNotNull('user_id')
            ->whereIn('id', $this->roster->studentIds($class))
            ->with('user')
            ->orderBy('name')->orderBy('id');
    }

    /**
     * Rekap presensi untuk satu halaman peserta, dihitung oleh `AttendanceReport` (aturan yang sama dengan halaman Academic).
     *
     * @param  LengthAwarePaginator<int, Student>  $students
     * @return array<int, array{counts: array<string, int>, total: int, attended: int, missed: int, percent: ?int, planned: int, allowed: int, remaining: int, level: string}>
     */
    public function attendanceFor(TeachingAssignment $class, LengthAwarePaginator $students): array
    {
        $tenant = Tenant::query()->findOrFail($this->tenantId());

        return $this->attendance->assignmentRecap(
            $class,
            $students->getCollection()->pluck('id'),
            (int) $tenant->setting('min_attendance_percent'),
            (int) $tenant->setting('meetings_per_course'),
        );
    }

    /**
     * Jumlah per halaman: `?per_page`, minimal 1 dan maksimal `integration.lms.max_per_page`.
     */
    public function perPage(Request $request): int
    {
        $max = (int) config('integration.lms.max_per_page');

        return max(1, min((int) $request->query('per_page', 50), $max));
    }

    /**
     * @return Builder<TeachingAssignment>
     */
    private function assignments(): Builder
    {
        return TeachingAssignment::query()->withoutGlobalScopes()->where('tenant_id', $this->tenantId());
    }
}
