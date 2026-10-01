<?php

namespace App\Modules\Payments\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentController {

    public function generateToken(): JsonResponse
    {
        $url_final = config('services.niubiz.url_api') . '/api.security/v1/security';
        $user = config('services.niubiz.user');
        $password = config('services.niubiz.password');

        $response = Http::withBasicAuth($user, $password)->get($url_final);

        if ($response->failed()) {
            Log::error('NIUBIZ token error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return response()->json([
                'message' => 'No se pudo generar el token de pago',
            ], 502);
        }

        return response()->json([
            'accessToken' => $response->body(),
        ]);
    }

}
