<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\GraduationDecision;
use App\Models\Yudisium;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class YudisiumPdfController extends Controller
{
    /**
     * Rekap keputusan yudisium (untuk staf).
     */
    public function recap(Yudisium $yudisium): Response
    {
        abort_unless(auth()->user()->hasRole(Role::Admin, Role::Principal, Role::Curriculum), 403);

        $decisions = $yudisium->decisions()->with('student.program')->get()->sortBy(fn (GraduationDecision $decision): string => ($decision->student->program?->name ?? '').$decision->student->name)->values();
        abort_if($decisions->isEmpty(), 404);

        return $this->pdf('pdf.yudisium', ['yudisium' => $yudisium, 'decisions' => $decisions], 'landscape', 'yudisium-'.Str::slug($yudisium->name).'.pdf');
    }

    /**
     * Surat keterangan lulus: milik mahasiswa itu sendiri atau staf akademik.
     */
    public function certificate(GraduationDecision $decision): Response
    {
        $user = auth()->user();

        abort_unless(
            $user->hasRole(Role::Admin, Role::Principal, Role::Curriculum)
            || ($user->hasRole(Role::Student) && $user->student?->id === $decision->student_id),
            403,
        );

        $decision->load(['student.program.faculty', 'yudisium']);

        return $this->pdf('pdf.graduation-certificate', ['decision' => $decision], 'portrait', 'skl-'.Str::slug($decision->student->nim).'.pdf');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function pdf(string $view, array $data, string $orientation, string $filename): Response
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);

        $pdf = new Dompdf($options);
        $pdf->loadHtml(view($view, $data + ['tenant' => auth()->user()->tenant, 'printedBy' => auth()->user()->name])->render(), 'UTF-8');
        $pdf->setPaper('A4', $orientation);
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
