<?php

namespace App\Http\Controllers;

use App\Models\LetterRequest;
use App\Services\LetterService;
use App\Support\QrCode;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LetterPdfController extends Controller
{
    /**
     * Surat yang sudah terbit, dicetak dari data beku saat terbit; hanya pemilik surat dan staf akademik.
     */
    public function __invoke(LetterRequest $letter, LetterService $service): Response
    {
        abort_unless($service->canView(auth()->user(), $letter), 403);
        abort_unless($letter->isIssued(), 404);

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);

        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('pdf.letter', [
            'letter' => $letter,
            'data' => $letter->snapshot,
            'tenant' => $letter->tenant,
            'qr' => QrCode::dataUri($letter->verificationUrl(), 5),
        ])->render(), 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($letter->type->code().'-'.$letter->snapshot['student']['nim'].'-'.$letter->number_seq).'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
