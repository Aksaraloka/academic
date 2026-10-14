<?php

namespace App\Http\Controllers;

use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    /**
     * Notifikasi pembayaran virtual account dari penyedia pembayaran, ditandatangani HMAC-SHA256 pada header X-Pay-Signature.
     */
    public function __invoke(Request $request, FinanceService $finance): JsonResponse
    {
        $result = $finance->handleWebhook($request->getContent(), $request->header('X-Pay-Signature'), $request->json()->all());

        return response()->json($result, match ($result['status']) {
            'ok', 'duplicate' => 200,
            'unauthorized' => 401,
            default => 422,
        });
    }
}
