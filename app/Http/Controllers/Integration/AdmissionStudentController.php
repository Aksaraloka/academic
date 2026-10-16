<?php

namespace App\Http\Controllers\Integration;

use App\Exceptions\AdmissionHandoffException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Integration\StoreAdmissionStudentRequest;
use App\Services\AdmissionHandoffService;
use App\Services\Integration\SyncReader;
use Illuminate\Http\JsonResponse;

class AdmissionStudentController extends Controller
{
    /**
     * Serah terima mahasiswa baru dari Admission. 201 untuk yang baru, 200 untuk `admission_ref` yang sudah pernah
     * diserahkan (isi sama, tanpa data baru); 409 `email_taken`/`nik_taken`; 404 prodi atau semester tenant lain.
     */
    public function store(StoreAdmissionStudentRequest $request, SyncReader $reader, AdmissionHandoffService $handoff): JsonResponse
    {
        try {
            $result = $handoff->handle($reader->tenantId(), $request->validated());
        } catch (AdmissionHandoffException $e) {
            return response()->json(['code' => $e->errorCode, 'message' => $e->getMessage()], $e->status);
        }

        return response()->json($result['data'], $result['created'] ? 201 : 200);
    }
}
