<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\StudyGroup;
use App\Models\Teacher;
use App\Services\ScheduleBoard;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ScheduleExportController extends Controller
{
    /**
     * Unduh jadwal mingguan sebagai PDF. Staf boleh memilih kelas, dosen, atau ruang;
     * mahasiswa dan orang tua hanya mendapat jadwal kelas mereka pada semester terpilih.
     */
    public function __invoke(Request $request, ScheduleBoard $board): Response
    {
        $user = $request->user();
        $input = $request->validate([
            'semester' => ['nullable', 'integer'],
            'view' => ['nullable', 'in:group,teacher,room'],
            'id' => ['nullable', 'integer'],
        ]);

        $term = $board->resolveTerm($board->terms(), $input['semester'] ?? null);
        abort_if($term === null, 404);

        $view = 'group';
        $targetId = '';

        if ($user->role->isStaff()) {
            $view = $input['view'] ?? 'group';
            $targetId = (string) ($input['id'] ?? '');
        }

        if ($targetId === '') {
            $default = $board->defaultTarget($user, $term->id, $view);
            $view = $default['view'];
            $targetId = $default['id'];
        }

        abort_if($targetId === '', 404);

        $label = match ($view) {
            'teacher' => Teacher::query()->findOrFail($targetId)->name,
            'room' => Room::query()->findOrFail($targetId)->name,
            default => StudyGroup::query()->where('academic_term_id', $term->id)->findOrFail($targetId)->name,
        };

        $data = $board->build($term, $view, $targetId);
        abort_if($data['stats']['meetings'] === 0, 404);

        $html = view('pdf.schedule', $data + [
            'term' => $term,
            'view' => $view,
            'viewLabel' => ['group' => 'Kelas', 'teacher' => 'Dosen', 'room' => 'Ruang'][$view],
            'targetLabel' => $label,
            'tenant' => $user->tenant,
            'printedBy' => $user->name,
        ])->render();

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        $filename = 'jadwal-'.Str::slug(str_replace('/', '-', "{$label} {$term->name} {$term->semester}")).'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
