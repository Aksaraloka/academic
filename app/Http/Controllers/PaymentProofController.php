<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PaymentProofController extends Controller
{
    /**
     * Bukti pembayaran privat; hanya pemilik tagihan dan petugas keuangan yang bisa membukanya.
     */
    public function __invoke(Payment $payment): Response
    {
        abort_unless(Invoice::query()->visibleTo(auth()->user())->whereKey($payment->invoice_id)->exists(), 404);
        abort_unless($payment->proof_path && Storage::disk('local')->exists($payment->proof_path), 404);

        $mime = match (strtolower(pathinfo($payment->proof_path, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return Storage::disk('local')->response($payment->proof_path, null, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}
