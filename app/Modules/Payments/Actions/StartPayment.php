<?php

namespace App\Modules\Payments\Actions;

use App\Modules\Cart\Services\CartService;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\NiubizClient;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Inicia un pago: fija el monto (foto del carrito), abre la sesión en Niubiz
 * y genera el purchase_number en el servidor. Ese número es la llave de idempotencia.
 */
class StartPayment
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private CartService $cartService,
        private NiubizClient $niubiz,
    ) {}

    /**
     * @return array{payment: Payment, sessionKey: string}
     */
    public function handle(int $userId, string $clientIp): array
    {
        // El monto se calcula en el server: nunca se confía en el que mande el cliente
        $totals = $this->cartService->totalsFor($userId);

        if ($totals['subtotal'] <= 0) {
            throw ValidationException::withMessages(['cart' => 'El carrito está vacío.']);
        }

        // Primero la sesión: si Niubiz falla no queda un pago huérfano
        $sessionKey = $this->niubiz->createSession($totals['total'], $clientIp);

        return [
            'payment' => $this->createPayment($userId, $totals['total']),
            'sessionKey' => $sessionKey,
        ];
    }

    private function createPayment(int $userId, float $amount): Payment
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return Payment::create([
                    'user_id' => $userId,
                    'purchase_number' => $this->randomPurchaseNumber(),
                    'amount' => $amount,
                    'currency' => 'PEN',
                ])->refresh();
            } catch (UniqueConstraintViolationException $e) {
                // Choque de número aleatorio (muy improbable): reintentar con otro
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    /** Niubiz exige un número de compra numérico de hasta 12 dígitos. */
    private function randomPurchaseNumber(): string
    {
        return (string) random_int(100_000_000_000, 999_999_999_999);
    }
}
