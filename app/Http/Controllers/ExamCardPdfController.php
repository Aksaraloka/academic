<?php

namespace App\Http\Controllers;

use App\Enums\ExamType;
use App\Models\AcademicTerm;
use App\Services\AcademicRecord;
use App\Services\ExamService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ExamCardPdfController extends Controller
{
    /**
     * Unduh kartu ujian; hanya memuat mata kuliah yang berhak diikuti dan sudah berjadwal.
     */
    public function __invoke(Request $request, ExamService $exams, AcademicRecord $records): Response
    {
        $student = $records->resolveStudent($request->user(), null, $request->integer('child') ?: null);
        $term = AcademicTerm::current();
        $type = ExamType::tryFrom((string) $request->query('type')) ?? ExamType::Midterm;

        abort_if($student === null || $term === null, 404);
        abort_if($exams->cardBlocker($student, $term) !== null, 403, 'Kartu ujian belum terbit.');

        $rows = $exams->cardRows($student, $term, $type)->filter(fn (array $row): bool => $row['exam'] !== null && $row['eligible'])->sortBy(fn (array $row) => $row['exam']->date->toDateString().$row['exam']->starts_at)->values();
        abort_if($rows->isEmpty(), 404);

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);

        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('pdf.exam-card', [
            'student' => $student->load('program.faculty'), 'term' => $term, 'type' => $type, 'rows' => $rows,
            'tenant' => $request->user()->tenant, 'printedBy' => $request->user()->name,
        ])->render(), 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="kartu-'.$type->value.'-'.Str::slug($student->nim).'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
