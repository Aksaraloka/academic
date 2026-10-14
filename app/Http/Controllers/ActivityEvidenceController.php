<?php

namespace App\Http\Controllers;

use App\Models\StudentActivity;
use App\Services\ActivityService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ActivityEvidenceController extends Controller
{
    /**
     * Bukti kegiatan privat; hanya pemilik dan staf akademik yang bisa membukanya.
     */
    public function __invoke(StudentActivity $activity, ActivityService $service): Response
    {
        $user = auth()->user();

        abort_unless($service->isManager($user) || ($user->student !== null && $user->student->id === $activity->student_id), 404);
        abort_unless($activity->evidence_path && Storage::disk('local')->exists($activity->evidence_path), 404);

        $mime = match (strtolower(pathinfo($activity->evidence_path, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return Storage::disk('local')->response($activity->evidence_path, null, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}
