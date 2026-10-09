<?php

namespace App\Modules\Shippings\Actions;

use App\Modules\Orders\Actions\UpdateOrderStatus;
use App\Modules\Orders\Enums\OrderStatusEnum;
use App\Modules\Orders\Models\Order;
use App\Modules\Shippings\Models\Shipping;
use Illuminate\Support\Facades\DB;

/**
 * Despachar una orden: crea el envío y le pide a Orders que la marque como `shipped`.
 * Shippings no decide si la orden puede despacharse; eso lo resuelve UpdateOrderStatus.
 */
class CreateShipping
{
    public function __construct(
        private UpdateOrderStatus $updateOrderStatus,
    ) {}

    public function handle(Order $order, int $driverId): Shipping
    {
        // Orden y envío cambian juntos: si falla uno, no queda ninguno a medias
        return DB::transaction(function () use ($order, $driverId) {
            $this->updateOrderStatus->handle($order, OrderStatusEnum::Shipped);

            // Shipping::create y no $order->shippings(): Orders no conoce a Shippings
            return Shipping::create([
                'order_id' => $order->id,
                'driver_id' => $driverId,
            ]);
        });
    }
}
