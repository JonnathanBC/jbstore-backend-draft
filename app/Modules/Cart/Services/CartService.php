<?php

namespace App\Modules\Cart\Services;

use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CartService
{
    public const INSTANCE = 'shopping';

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
     * Subtotal del carrito guardado del usuario (sin el tax del paquete).
     */
    public function subtotal(int $userId): float
    {
        $this->restore($userId);

        return (float) Cart::instance(self::INSTANCE)->subtotal(2, '.', '');
    }
}
