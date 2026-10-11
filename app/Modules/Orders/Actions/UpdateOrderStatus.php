<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\OrderStatusEnum;
use App\Modules\Orders\Events\OrderStatusChanged;use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;
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
                'status' => $order->status->transitionErrorTo($next),
            ]);
        }

        // Orden y reacciones de otros módulos cambian juntas: si falla un listener, no queda nada a medias
        return DB::transaction(function () use ($order, $next) {
            $previous = $order->status;

            $order->status = $next;
            $order->save();

            OrderStatusChanged::dispatch($order, $previous, $next);

            return $order;
        });
    }
}
