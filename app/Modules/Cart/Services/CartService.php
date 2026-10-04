<?php

namespace App\Modules\Cart\Services;

use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CartService
{
    public const INSTANCE = 'shopping';

    // Única fuente de verdad del costo de envío.
    public const SHIPPING_COST = 3.0;

    /**
     * Carga el carrito guardado del usuario en la sesión del request.
     */
    public function restore(int $userId): void
    {
        $stored = DB::table(config('cart.database.table'))
            ->where('identifier', $userId)
            ->where('instance', self::INSTANCE)
            ->first();

        if (! $stored) {
            return;
        }

        $serialized = base64_decode($stored->content, true);
        $storedContent = $serialized === false ? false : @unserialize($serialized);

        if (! $storedContent instanceof Collection) {
            DB::table(config('cart.database.table'))
                ->where('identifier', $userId)
                ->where('instance', self::INSTANCE)
                ->delete();

            return;
        }

        $cart = Cart::instance(self::INSTANCE);
        $cart->destroy();

        foreach ($storedContent as $cartItem) {
            $cart->add($cartItem);
        }
    }

    /**
     * Guarda el carrito en DB (store borra el registro previo e inserta el nuevo).
     */
    public function persist(int $userId): void
    {
        $content = base64_encode(serialize(Cart::instance(self::INSTANCE)->content()));

        DB::table(config('cart.database.table'))->updateOrInsert(
            [
                'identifier' => $userId,
                'instance' => self::INSTANCE,
            ],
            [
                'content' => $content,
                'created_at' => now(),
            ],
        );
    }

    /**
     * Totales del carrito ya cargado en la sesión. Sin items no se cobra envío.
     *
     * @return array{subtotal: float, shipping: float, total: float}
     */
    public function totals(): array
    {
        $subtotal = (float) Cart::instance(self::INSTANCE)->subtotal(2, '.', '');
        $shipping = $subtotal > 0 ? self::SHIPPING_COST : 0.0;

        return [
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'total' => round($subtotal + $shipping, 2),
        ];
    }

    /**
     * Totales del carrito guardado del usuario (lo restaura antes de calcular).
     *
     * @return array{subtotal: float, shipping: float, total: float}
     */
    public function subtotal(int $userId): float
    {
        $this->restore($userId);

        return (float) Cart::instance(self::INSTANCE)->subtotal(2, '.', '');
    }

    public function totalsFor(int $userId): array
    {
        $this->restore($userId);

        return $this->totals();
    }

    /**
     * Vacía el carrito del usuario (sesión y DB). Se usa tras un pago aprobado.
     */
    public function clear(int $userId): void
    {
        Cart::instance(self::INSTANCE)->destroy();
        $this->persist($userId);
    }

    public function count(int $userId): int
    {
        $this->restore($userId);

        return Cart::instance(self::INSTANCE)->count();
    }

    public function contentFor(): Collection
    {
        return Cart::instance(self::INSTANCE)->content();
    }
}
