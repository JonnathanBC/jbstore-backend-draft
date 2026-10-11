<?php

namespace Tests\Feature\Shippings;

use App\Modules\Drivers\Models\Driver;
use App\Modules\Orders\Enums\OrderStatusEnum;
use App\Modules\Orders\Models\Order;
use App\Modules\Shippings\Models\Shipping;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarkShippingAsRefundedTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(User $owner, OrderStatusEnum $status = OrderStatusEnum::Failed): Order
    {
        // Sin eventos: el observer genera el PDF del ticket y no es parte de este test
        return Order::withoutEvents(function () use ($owner, $status) {
            $order = Order::create([
                'user_id' => $owner->id,
                'payment_id' => 'pay_123',
                'content' => [],
                'address' => [],
                'total' => 100,
            ]);
            $order->status = $status;
            $order->save();

            return $order;
        });
    }

    private function makeShipping(Order $order): Shipping
    {
        $driver = Driver::create([
            'user_id' => User::factory()->create()->id,
            'type' => 'car',
            'license_plate' => 'ABC-123',
        ]);

        return Shipping::create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
        ]);
    }

    public function test_refunding_failed_order_marks_its_latest_shipping_as_refunded(): void
    {
        // freezeSecond y no freezeTime: el timestamp de la base no guarda microsegundos
        $this->freezeSecond();

        $order = $this->makeOrder(User::factory()->create());
        $oldShipping = $this->makeShipping($order);

        // latest() ordena por created_at: el segundo envío tiene que ser más nuevo
        $this->travel(1)->minute();
        $latestShipping = $this->makeShipping($order);

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'refunded'])
            ->assertOk()
            ->assertJsonPath('status', 'refunded');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'refunded']);
        $this->assertTrue($latestShipping->fresh()->refunded_at->equalTo(now()));
        $this->assertNull($oldShipping->fresh()->refunded_at);
    }

    public function test_refunding_order_without_shipping_still_refunds_the_order(): void
    {
        $order = $this->makeOrder(User::factory()->create());

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'refunded'])
            ->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'refunded']);
    }

    public function test_order_not_failed_cannot_be_refunded_and_shipping_is_untouched(): void
    {
        $order = $this->makeOrder(User::factory()->create(), OrderStatusEnum::Shipped);
        $shipping = $this->makeShipping($order);

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'refunded'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'shipped']);
        $this->assertNull($shipping->fresh()->refunded_at);
    }

    public function test_other_order_transitions_do_not_mark_shipping_as_refunded(): void
    {
        $order = $this->makeOrder(User::factory()->create(), OrderStatusEnum::Shipped);
        $shipping = $this->makeShipping($order);

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'failed'])
            ->assertOk();

        $this->assertNull($shipping->fresh()->refunded_at);
    }
}
