<?php

namespace App\Http\Controllers;

use App\Models\GraduationNews;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class GraduationPosterController extends Controller
{
    /**
     * Poster disimpan di disk privat dan hanya disajikan lewat rute yang memeriksa hak akses.
     */
    public function __invoke(GraduationNews $news): Response
    {
        abort_unless(GraduationNews::query()->visibleTo(auth()->user())->whereKey($news->id)->exists(), 404);
        abort_unless($news->hasPoster() && Storage::disk('local')->exists($news->poster_path), 404);

        $extension = strtolower(pathinfo($news->poster_path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return Storage::disk('local')->response($news->poster_path, null, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}
