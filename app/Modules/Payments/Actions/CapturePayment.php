<?php

namespace App\Modules\Payments\Actions;

use App\Modules\Cart\Services\CartService;
use App\Modules\Orders\Actions\CreateOrderFromCart;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\NiubizClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cobra un pago iniciado con StartPayment. Idempotente por purchase_number:
 * llamarlo N veces cobra como máximo UNA y devuelve siempre el mismo resultado.
 */
class CapturePayment
{
    /** Segundos que se retiene el lock (más que el timeout de Niubiz). */
    private const LOCK_SECONDS = 60;

    /** Segundos que un segundo request espera a que termine el primero. */
    private const WAIT_SECONDS = 20;

    public function __construct(
        private CartService $cartService,
        private NiubizClient $niubiz,
        private CreateOrderFromCart $createOrderFromCart,
    ) {}

    /**
     * @throws \Illuminate\Contracts\Cache\LockTimeoutException si otro request lo está procesando hace demasiado
     */
    public function handle(int $userId, string $purchaseNumber, string $transactionToken): Payment
    {
        // Doble click, refresh, dos pestañas: el segundo request ESPERA acá al primero
        return Cache::lock("payments:capture:{$purchaseNumber}", self::LOCK_SECONDS)
            ->block(self::WAIT_SECONDS, fn () => $this->capture($userId, $purchaseNumber, $transactionToken));
    }

    private function capture(int $userId, string $purchaseNumber, string $transactionToken): Payment
    {
        $payment = Payment::query()
            ->where('purchase_number', $purchaseNumber)
            ->where('user_id', $userId) // nunca el pago de otro usuario: 404
            ->firstOrFail();

        // Replay: ya se procesó → mismo resultado, sin volver a cobrar
        if (! $payment->isPending()) {
            return $payment;
        }

        // Si el carrito cambió desde que se inició el pago, no se cobra un monto viejo
        $total = $this->cartService->totalsFor($userId)['total'];

        if (! $payment->hasAmount($total)) {
            throw ValidationException::withMessages([
                'cart' => 'Tu carrito cambió desde que iniciaste el pago. Revisalo y volvé a pagar.',
            ]);
        }

        $result = $this->niubiz->authorize($purchaseNumber, $transactionToken, (float) $payment->amount);

        if ($result->isUnknown()) {
            // No sabemos si cobró: queda en `error` y no se reintenta. Se reconcilia.
            $payment->markAsError($result->message);

            return $payment;
        }

        if ($result->rejected) {
            $payment->markAsRejected($result);

            return $payment;
        }

        // Se guarda el cobro ANTES de crear la orden: si lo siguiente falla,
        // queda un pago `authorized` sin orden, detectable para reconciliar.
        $payment->markAsAuthorized($result);

        DB::transaction(function () use ($payment, $userId) {
            $order = $this->createOrderFromCart->handle($payment->provider_transaction_id, $userId);
            $payment->attachOrder($order);
        });

        return $payment;
    }
}
