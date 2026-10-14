<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\TeachingAssignment;
use App\Services\JournalService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class JournalPdfController extends Controller
{
    /**
     * Berita Acara Perkuliahan (BAP) satu kuliah: materi tiap pertemuan beserta kehadiran.
     */
    public function __invoke(TeachingAssignment $assignment, JournalService $journals): Response
    {
        $user = auth()->user();

        abort_unless(
            $user->hasRole(Role::Admin, Role::Principal, Role::Curriculum)
            || ($user->hasRole(Role::Teacher) && $assignment->teacher_id === $user->teacher?->id),
            403,
        );

        $assignment->load(['subject', 'teacher', 'studyGroup', 'academicTerm']);
        $meetings = $journals->meetings($assignment);
        abort_if($meetings->isEmpty(), 404);

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);

        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('pdf.journal', ['assignment' => $assignment, 'meetings' => $meetings, 'tenant' => $user->tenant, 'printedBy' => $user->name])->render(), 'UTF-8');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="bap-'.Str::slug($assignment->subject->code.' '.$assignment->studyGroup->name).'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
