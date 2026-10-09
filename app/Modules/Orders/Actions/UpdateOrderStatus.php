<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\OrderStatusEnum;
use App\Modules\Orders\Models\Order;
use Illuminate\Validation\ValidationException;

/**
 * Único punto de entrada para cambiar el estado de una orden.
 * Lo usa el propio módulo (OrderController) y cualquier otro módulo (Shippings, Payments...):
 * la regla de transición vive acá y en el enum, en ningún otro lado.
 */
class UpdateOrderStatus
{
    public function handle(Order $order, OrderStatusEnum $next): Order
    {
        if (! $order->status->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => "No se puede pasar de {$order->status->value} a {$next->value}.",
            ]);
        }

        $order->status = $next;
        $order->save();

        return $order;
    }
}
