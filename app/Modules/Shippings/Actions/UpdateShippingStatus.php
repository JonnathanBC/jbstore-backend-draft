<?php

namespace App\Modules\Shippings\Actions;

use App\Modules\Orders\Actions\UpdateOrderStatus;
use App\Modules\Orders\Enums\OrderStatusEnum;
use App\Modules\Shippings\Enums\ShippingStatusEnum;
use App\Modules\Shippings\Models\Shipping;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Único punto de entrada para cambiar el estado de un envío.
 * La regla de transición vive en el enum; los efectos secundarios de cada estado, acá.
 */
class UpdateShippingStatus
{
    public function __construct(private UpdateOrderStatus $updateOrderStatus) {}

    public function handle(Shipping $shipping, ShippingStatusEnum $next): Shipping
    {
        if (! $shipping->status->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => "No se puede pasar el envío de {$shipping->status->value} a {$next->value}.",
            ]);
        }

        return DB::transaction(function () use ($shipping, $next) {
            match ($next) {
                ShippingStatusEnum::Completed => $this->complete($shipping),
                ShippingStatusEnum::Failed => $this->failed($shipping),
                default => null,
            };

            $shipping->status = $next;
            $shipping->save();

            return $shipping;
        });
    }

    private function complete(Shipping $shipping): void
    {
        $shipping->delivered_at = now();
        $this->updateOrderStatus->handle($shipping->order, OrderStatusEnum::Completed);
    }

    private function failed(Shipping $shipping): void
    {
        $this->updateOrderStatus->handle($shipping->order, OrderStatusEnum::Failed);
    }
}
