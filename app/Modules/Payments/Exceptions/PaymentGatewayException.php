<?php

namespace App\Modules\Payments\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * La pasarela no respondió en un paso SEGURO de reintentar (token, sesión).
 * Nunca se usa para la autorización: ahí un fallo deja el pago en `error`.
 */
class PaymentGatewayException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 502);
    }
}
