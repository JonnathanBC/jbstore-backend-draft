<?php

namespace App\Modules\Orders\Events;

use App\Modules\Orders\Enums\OrderStatusEnum;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Orders avisa que una orden cambió de estado; no sabe ni le importa quién escucha.
 * Así otros módulos (Shippings, Payments...) reaccionan sin que Orders los conozca.
 */
class OrderStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly OrderStatusEnum $previous,
        public readonly OrderStatusEnum $next,
    ) {}
}
