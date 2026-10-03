<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Addresses\Models\Address;
use App\Modules\Cart\Services\CartService;
use App\Modules\Orders\Models\Order;

class CreateOrderFromCart
{
    public function __construct(
        private CartService $cartService
    ) {}

    public function handle(string $paymentId, int $userId): Order
    {
        $address = Address::where('is_default', true)->first();
        $totals = $this->cartService->totals();

        $order = Order::create([
            'content' => $this->cartService->contentFor(),
            'address' => $address,
            'payment_id' => $paymentId,
            'total' => $totals['total'],
        ]);

        $this->cartService->clear($userId);

        return $order;
    }
}
