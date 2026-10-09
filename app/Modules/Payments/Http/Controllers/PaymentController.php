<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Modules\Payments\Actions\CapturePayment;
use App\Modules\Payments\Actions\StartPayment;
use App\Modules\Payments\Enums\PaymentStatusEnum;
use App\Modules\Payments\Http\Requests\CapturePaymentRequest;
use App\Modules\Payments\Services\NiubizClient;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController
{
    public function generateToken(NiubizClient $niubiz): JsonResponse
    {
        return response()->json(['accessToken' => $niubiz->accessToken()]);
    }

    public function generateTokenSession(Request $request, StartPayment $startPayment): JsonResponse
    {
        ['payment' => $payment, 'sessionKey' => $sessionKey] = $startPayment->handle(
            $request->user()->id,
            $request->ip(),
        );

        return response()->json([
            'sessionKey' => $sessionKey,
            'purchaseNumber' => $payment->purchase_number,
            'amount' => (float) $payment->amount,
        ]);
    }

    public function capturePayment(CapturePaymentRequest $request, CapturePayment $capturePayment): JsonResponse
    {
        try {
            $payment = $capturePayment->handle(
                $request->user()->id,
                $request->validated('purchaseNumber'),
                $request->validated('transactionToken'),
            );
        } catch (LockTimeoutException) {
            return response()->json([
                'message' => 'Tu pago se está procesando. Esperá unos segundos y revisá tus pedidos.',
            ], 409);
        }

        return match ($payment->status) {
            PaymentStatusEnum::Authorized => response()->json($payment->provider_response),

            // Un rechazo NO puede responder 200: el front lo tomaría como pago exitoso
            PaymentStatusEnum::Rejected => response()->json([
                'message' => $payment->error_message,
                'data' => $payment->provider_response['data'] ?? null,
            ], 402),

            PaymentStatusEnum::Error => response()->json([
                'message' => 'No pudimos confirmar tu pago. No lo reintentes: lo estamos verificando.',
            ], 502),

            PaymentStatusEnum::Pending => response()->json([
                'message' => 'El pago todavía no se procesó.',
            ], 409),
        };
    }
}
