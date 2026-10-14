<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\ActivityService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SkpiPdfController extends Controller
{
    /**
     * Rekap aktivitas terverifikasi (bahan SKPI): milik mahasiswa itu sendiri, atau mahasiswa mana pun bagi staf akademik.
     */
    public function __invoke(ActivityService $service, ?Student $student = null): Response
    {
        $user = auth()->user();

        if ($student === null) {
            $student = $user->student;
            abort_if($student === null, 404);
        } else {
            abort_unless($service->isManager($user) || $user->student?->id === $student->id, 403);
        }

        $student->load('program.faculty');

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);

        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('pdf.skpi', [
            'student' => $student,
            'skpi' => $service->skpi($student),
            'tenant' => $student->tenant,
            'printedBy' => $user->name,
        ])->render(), 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="skpi-'.Str::slug($student->nim).'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
