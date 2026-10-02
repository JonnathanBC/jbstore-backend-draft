<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Modules\Cart\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentController {

    public function __construct(private CartService $cartService) {}

    public function generateToken(): JsonResponse
    {
        $access_token = $this->requestAccessToken();

        if (! $access_token) {
            return response()->json([
                'message' => 'No se pudo generar el token de pago',
            ], 502);
        }

        return response()->json([
            'accessToken' => $access_token,
        ]);
    }

    public function generateTokenSession(Request $request): JsonResponse
    {
        // El monto se calcula en el server: nunca se confía en el que mande el cliente.
        $amount = $this->cartService->subtotal($request->user()->id);

        if ($amount <= 0) {
            return response()->json([
                'message' => 'El carrito está vacío',
            ], 422);
        }

        $access_token = $this->requestAccessToken();

        if (! $access_token) {
            return response()->json([
                'message' => 'No se pudo generar el token de pago',
            ], 502);
        }

        $merchant_id = config('services.niubiz.merchant_id');
        $url_final = config('services.niubiz.url_api') . "/api.ecommerce/v2/ecommerce/token/session/{$merchant_id}";

        $response = Http::withHeaders([
            'Authorization' => $access_token,
            'Content-Type' => 'application/json',
        ])
        ->post($url_final, [
            'channel' => 'web',
            'amount' => $amount,
            'antifraud' => [
                'clientIp' => request()->ip(),
                'merchantDefineData' => [
                    'MDD4'  => 'integraciones@niubiz.com.pe',
                    'MDD32' => 'JD1892639123',
                    'MDD75' => 'Registrado',
                    'MDD77' => 458
                ],
            ],
        ]);

        if ($response->failed()) {
            return response()->json([
                'message' => 'No se pudo iniciar la sesión de pago',
            ], 502);
        }

        return response()->json([
            'sessionKey' => $response->json('sessionKey'),
            'amount' => $amount,
        ]);
    }

    /**
     * Pide el access token a Niubiz (responde text/plain). Devuelve null si falla.
     */
    private function requestAccessToken(): ?string
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

            return null;
        }

        return $response->body();
    }

}
