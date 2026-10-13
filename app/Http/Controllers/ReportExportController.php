<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Models\AcademicTerm;
use App\Models\StudyGroup;
use App\Services\AttendanceReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    /**
     * Ekspor rekap kehadiran satu kelas (per semester atau per bulan, opsional per mata kuliah) sebagai CSV (P-17).
     */
    public function __invoke(Request $request, AttendanceReport $report): StreamedResponse
    {
        abort_unless($request->user()->hasRole(Role::Admin, Role::Principal, Role::Curriculum, Role::Teacher, Role::Duty), 403);

        $data = $request->validate([
            'group' => ['required', 'integer'],
            'matkul' => ['nullable', 'integer'],
            'bulan' => ['nullable', 'date_format:Y-m'],
        ]);

        $group = StudyGroup::query()->visibleTo($request->user())->findOrFail($data['group']);

        if (! empty($data['bulan'])) {
            $from = CarbonImmutable::createFromFormat('Y-m', $data['bulan'])->startOfMonth();
            $to = $from->endOfMonth();
            $label = $data['bulan'];
        } else {
            $term = AcademicTerm::current();
            $from = CarbonImmutable::parse($term?->starts_at ?? now()->startOfYear());
            $to = CarbonImmutable::parse($term?->ends_at ?? now()->endOfYear());
            $label = 'semester';
        }

        $recap = $report->groupRecap($group, $from, $to, isset($data['matkul']) ? (int) $data['matkul'] : null);
        $statuses = $request->user()->attendanceStatuses();
        $minPercent = (int) $request->user()->tenant->setting('min_attendance_percent');

        return response()->streamDownload(function () use ($recap, $statuses, $minPercent): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Nama', 'NIM', ...array_map(fn (AttendanceStatus $status): string => $status->label(), $statuses), 'Persen hadir', 'Syarat ujian']);

            foreach ($recap as $row) {
                $counts = array_map(fn (AttendanceStatus $status): int => $row['counts'][$status->value], $statuses);
                $eligible = $row['percent'] === null ? '' : ($row['percent'] >= $minPercent ? 'Memenuhi' : 'Belum memenuhi');

                fputcsv($out, [$row['student']->name, $row['student']->nim, ...$counts, $row['percent'] ?? '', $eligible]);
            }

            fclose($out);
        }, "rekap-{$group->name}-{$label}.csv", ['Content-Type' => 'text/csv']);
    }
}
