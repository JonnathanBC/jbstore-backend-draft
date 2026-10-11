<?php

namespace App\Modules\Shippings\Listeners;

use App\Modules\Orders\Enums\OrderStatusEnum;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Shippings\Models\Shipping;

class MarkShippingAsRefundedListener
{
    public function handle(OrderStatusChanged $event): void
    {
        if ($event->next !== OrderStatusEnum::Refunded) {
            return;
        }

        // Shipping::where y no $order->shippings(): Orders no conoce a Shippings
        $shipping = Shipping::where('order_id', $event->order->id)
            ->latest()
            ->first();

        if (! $shipping) {
            return;
        }

        $shipping->refunded_at = now();
        $shipping->save();
    }
}
