<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Http\Resources\Integration\UserResource;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * Profil pengguna yang masuk lewat SSO.
     */
    public function __invoke(Request $request): UserResource
    {
        return new UserResource($request->user()->load(['student.program', 'teacher']));
    }
}
