<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Student;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class StudentPhotoController extends Controller
{
    /**
     * Pas foto di disk privat; hanya pemilik foto dan staf akademik kampus yang sama yang boleh melihatnya.
     */
    public function __invoke(Student $student): Response
    {
        $user = auth()->user();
        $isOwner = $student->user_id !== null && $student->user_id === $user->id;

        abort_unless($isOwner || $user->hasRole(Role::Admin, Role::Principal, Role::Curriculum, Role::Teacher, Role::Duty), 404);
        abort_unless($student->hasPhoto(), 404);

        return Storage::disk('local')->response($student->photo_path, null, [
            'Content-Type' => str_ends_with($student->photo_path, '.png') ? 'image/png' : 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
