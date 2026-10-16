<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Services\IntegrationApps;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppsController extends Controller
{
    /**
     * Aplikasi Aksaraloka yang boleh dibuka pengguna ini (untuk pengalih aplikasi di Learn dan Admission).
     * `academic` selalu ada; aplikasi lain hanya bila terkonfigurasi dan peran pengguna diizinkan.
     */
    public function __invoke(Request $request, IntegrationApps $apps): JsonResponse
    {
        return response()->json(['data' => $apps->linksFor($request->user())]);
    }
}
