<?php

namespace App\Http\Controllers;

use App\Models\FeedbackTicket;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class FeedbackAttachmentController extends Controller
{
    /**
     * Lampiran disimpan privat; hanya pengirim dan penangani yang bisa membukanya.
     */
    public function __invoke(FeedbackTicket $ticket): Response
    {
        abort_unless(FeedbackTicket::query()->visibleTo(auth()->user())->whereKey($ticket->id)->exists(), 404);
        abort_unless($ticket->attachment_path && Storage::disk('local')->exists($ticket->attachment_path), 404);

        $mime = match (strtolower(pathinfo($ticket->attachment_path, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return Storage::disk('local')->response($ticket->attachment_path, null, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}
