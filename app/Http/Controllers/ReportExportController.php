<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Models\StudyGroup;
use App\Services\AttendanceReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    /**
     * Ekspor rekap kehadiran satu rombel per bulan sebagai CSV yang bisa dibuka di Excel (P-17).
     */
    public function __invoke(Request $request, AttendanceReport $report): StreamedResponse
    {
        abort_unless($request->user()->hasRole(Role::Admin, Role::Principal, Role::Curriculum, Role::Teacher, Role::Duty), 403);

        $data = $request->validate([
            'group' => ['required', 'integer'],
            'bulan' => ['required', 'date_format:Y-m'],
        ]);

        $group = StudyGroup::query()->visibleTo($request->user())->findOrFail($data['group']);
        $from = CarbonImmutable::createFromFormat('Y-m', $data['bulan'])->startOfMonth();
        $recap = $report->groupRecap($group, $from, $from->endOfMonth());

        return response()->streamDownload(function () use ($recap): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Nama', 'NIS', ...array_map(fn (AttendanceStatus $status): string => $status->label(), AttendanceStatus::cases()), 'Persen hadir']);

            foreach ($recap as $row) {
                fputcsv($out, [$row['student']->name, $row['student']->nis, ...array_values($row['counts']), $row['percent'] ?? '']);
            }

            fclose($out);
        }, "rekap-{$group->name}-{$data['bulan']}.csv", ['Content-Type' => 'text/csv']);
    }
}
