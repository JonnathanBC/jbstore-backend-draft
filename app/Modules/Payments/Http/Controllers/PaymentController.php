<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Modules\Cart\Services\CartService;
use App\Modules\Orders\Actions\CreateOrderFromCart;
use App\Modules\Payments\Http\Requests\CapturePaymentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentController {

    public function __construct(
        private CartService $cartService,
        private CreateOrderFromCart $createOrderFromCart
        ) {}

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
        $totals = $this->cartService->totalsFor($request->user()->id);
        $amount = $totals['total'];

        if ($totals['subtotal'] <= 0) {
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

    public function capturePayment(CapturePaymentRequest $request)
    {
        $data = $request->validated();
        $userId = $request->user()->id;

        // El monto sale del carrito, no del request: el cliente puede manipularlo.
        $totals = $this->cartService->totalsFor($userId);
        $amount = $totals['total'];

        if ($totals['subtotal'] <= 0) {
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
        $url_final = config('services.niubiz.url_api') . "/api.authorization/v3/authorization/ecommerce/{$merchant_id}";

        $response = Http::withHeaders([
            'Authorization' => $access_token,
            'Content-Type' => 'application/json',
        ])->post($url_final, [
            "channel" => "web",
            "captureType" => "manual",
            "countable" => true,
            "order"  => [
                "tokenId" => $data["transactionToken"],
                "purchaseNumber" => $data["purchaseNumber"],
                "amount" => $amount,
                "currency" => "PEN"
            ]
        ])->json();

        if (($response['dataMap']['ACTION_CODE'] ?? null) === '000') {

            $payment_id = $response['dataMap']['TRANSACTION_ID'] ?? null;

            $this->createOrderFromCart->handle($payment_id, $userId);

            return response()->json($response);
        }

        // Un rechazo NO puede responder 200: el front lo tomaría como pago exitoso.
        // `data` puede no venir (ej: error de autenticación): el front lo trata como opcional.
        return response()->json([
            'message' => $response['data']['ACTION_DESCRIPTION']
                ?? $response['errorMessage']
                ?? 'El pago fue rechazado',
            'data' => $response['data'] ?? null,
        ], 402);

    }
}
