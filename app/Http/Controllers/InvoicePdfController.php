<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class InvoicePdfController extends Controller
{
    /**
     * Unduh tagihan beserta riwayat pembayarannya sebagai PDF.
     */
    public function __invoke(Invoice $invoice): Response
    {
        abort_unless(Invoice::query()->visibleTo(auth()->user())->whereKey($invoice->id)->exists(), 404);

        $invoice->load(['student.program', 'academicTerm', 'items', 'payments']);

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);

        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('pdf.invoice', ['invoice' => $invoice, 'tenant' => auth()->user()->tenant, 'printedBy' => auth()->user()->name])->render(), 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($invoice->number).'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
