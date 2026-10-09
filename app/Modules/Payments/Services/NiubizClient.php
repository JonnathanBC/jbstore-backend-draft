<?php

namespace App\Modules\Payments\Services;

use App\Modules\Payments\Data\AuthorizationResult;
use App\Modules\Payments\Exceptions\PaymentGatewayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Único lugar que habla HTTP con Niubiz. Nada de tarjetas pasa por acá:
 * solo el transactionToken de un solo uso que genera el formulario de Niubiz.
 */
class NiubizClient
{
    private const TIMEOUT_SECONDS = 15;

    public function accessToken(): string
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withBasicAuth(config('services.niubiz.user'), config('services.niubiz.password'))
                ->get($this->url('/api.security/v1/security'));
        } catch (ConnectionException) {
            throw new PaymentGatewayException('No se pudo generar el token de pago');
        }

        if ($response->failed()) {
            Log::error('Niubiz: error al pedir el access token', ['status' => $response->status()]);

            throw new PaymentGatewayException('No se pudo generar el token de pago');
        }

        return $response->body();
    }

    public function createSession(float $amount, string $clientIp): string
    {
        $merchantId = config('services.niubiz.merchant_id');

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['Authorization' => $this->accessToken()])
                ->post($this->url("/api.ecommerce/v2/ecommerce/token/session/{$merchantId}"), [
                    'channel' => 'web',
                    'amount' => $amount,
                    'antifraud' => [
                        'clientIp' => $clientIp,
                        'merchantDefineData' => [
                            'MDD4' => 'integraciones@niubiz.com.pe',
                            'MDD32' => 'JD1892639123',
                            'MDD75' => 'Registrado',
                            'MDD77' => 458,
                        ],
                    ],
                ]);
        } catch (ConnectionException) {
            throw new PaymentGatewayException('No se pudo iniciar la sesión de pago');
        }

        if ($response->failed() || ! $response->json('sessionKey')) {
            Log::error('Niubiz: error al crear la sesión', ['status' => $response->status()]);

            throw new PaymentGatewayException('No se pudo iniciar la sesión de pago');
        }

        return $response->json('sessionKey');
    }

    /**
     * Cobra. NUNCA reintentar automáticamente: si no hubo respuesta clara,
     * el cobro pudo haberse hecho y un reintento cobraría dos veces.
     */
    public function authorize(string $purchaseNumber, string $transactionToken, float $amount): AuthorizationResult
    {
        $merchantId = config('services.niubiz.merchant_id');
        $accessToken = $this->accessToken(); // si falla acá, todavía no se cobró nada: seguro

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['Authorization' => $accessToken])
                ->post($this->url("/api.authorization/v3/authorization/ecommerce/{$merchantId}"), [
                    'channel' => 'web',
                    'captureType' => 'manual',
                    'countable' => true,
                    'order' => [
                        'tokenId' => $transactionToken,
                        'purchaseNumber' => $purchaseNumber,
                        'amount' => $amount,
                        'currency' => 'PEN',
                    ],
                ]);
        } catch (ConnectionException $e) {
            Log::error('Niubiz: sin respuesta al autorizar', ['purchase_number' => $purchaseNumber]);

            return AuthorizationResult::unknown('Sin respuesta de la pasarela al autorizar');
        }

        $body = $response->json();

        if ($response->serverError() || ! is_array($body)) {
            Log::error('Niubiz: respuesta inválida al autorizar', [
                'purchase_number' => $purchaseNumber,
                'status' => $response->status(),
            ]);

            return AuthorizationResult::unknown('Respuesta inválida de la pasarela al autorizar');
        }

        $body = $this->withoutSensitiveData($body);

        if (($body['dataMap']['ACTION_CODE'] ?? null) === '000') {
            return AuthorizationResult::approved(
                transactionId: (string) $body['dataMap']['TRANSACTION_ID'],
                cardMasked: $body['dataMap']['CARD'] ?? null,
                cardBrand: $body['dataMap']['BRAND'] ?? null,
                response: $body,
            );
        }

        return AuthorizationResult::rejected(
            message: $body['data']['ACTION_DESCRIPTION'] ?? $body['errorMessage'] ?? 'El pago fue rechazado',
            cardMasked: $body['data']['CARD'] ?? null,
            cardBrand: $body['data']['BRAND'] ?? null,
            response: $body,
        );
    }

    /** El transactionToken vuelve en `order.tokenId`: no se guarda ni se devuelve. */
    private function withoutSensitiveData(array $body): array
    {
        unset($body['order']['tokenId']);

        return $body;
    }

    private function url(string $path): string
    {
        return config('services.niubiz.url_api').$path;
    }
}
