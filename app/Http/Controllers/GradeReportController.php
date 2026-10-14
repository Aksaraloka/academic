<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\AcademicRecord;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class GradeReportController extends Controller
{
    /**
     * Unduh KHS satu semester sebagai PDF.
     */
    public function khs(Request $request, AcademicRecord $records): Response
    {
        $student = $this->student($request, $records);
        $term = $records->defaultTerm($student, $request->integer('semester') ?: null);

        abort_if($term === null, 404);

        $khs = $records->khs($student, $term);
        abort_if($khs['rows']->isEmpty(), 404);

        return $this->pdf('pdf.khs', [
            'student' => $student,
            'khs' => $khs,
            'tenant' => $request->user()->tenant,
            'printedBy' => $request->user()->name,
        ], 'khs-'.Str::slug($student->nim.' '.str_replace('/', '-', "{$term->name} {$term->semester}")).'.pdf');
    }

    /**
     * Unduh transkrip nilai lengkap sebagai PDF.
     */
    public function transcript(Request $request, AcademicRecord $records): Response
    {
        $student = $this->student($request, $records);
        $transcript = $records->transcript($student);

        abort_if($transcript['credits_counted'] === 0, 404);

        return $this->pdf('pdf.transcript', [
            'student' => $student,
            'transcript' => $transcript,
            'tenant' => $request->user()->tenant,
            'printedBy' => $request->user()->name,
        ], 'transkrip-'.Str::slug($student->nim.' '.$student->name).'.pdf');
    }

    private function student(Request $request, AcademicRecord $records): Student
    {
        $student = $records->resolveStudent($request->user(), $request->integer('mahasiswa') ?: null, $request->integer('anak') ?: null);

        abort_if($student === null, 404);

        return $student->load('program.faculty');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function pdf(string $view, array $data, string $filename): Response
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);

        $pdf = new Dompdf($options);
        $pdf->loadHtml(view($view, $data)->render(), 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
