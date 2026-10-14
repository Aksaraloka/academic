<?php

namespace App\Http\Controllers;

use App\Services\LetterService;
use Illuminate\Contracts\View\View;

class LetterVerificationController extends Controller
{
    /**
     * Halaman publik tujuan QR pada surat: menegaskan keaslian tanpa membuka data akademik.
     */
    public function __invoke(string $code, LetterService $service): View
    {
        $letter = $service->verify($code);

        return view('letters.verify', [
            'letter' => $letter,
            'tenant' => $letter?->tenant,
            'maskedNim' => $letter ? substr($letter->snapshot['student']['nim'], 0, 3).str_repeat('•', max(strlen($letter->snapshot['student']['nim']) - 5, 0)).substr($letter->snapshot['student']['nim'], -2) : null,
        ]);
    }
}
