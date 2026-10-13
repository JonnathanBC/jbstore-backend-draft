<?php

namespace Tests\Feature\Orders;

use App\Modules\Drivers\Models\Driver;
use App\Modules\Orders\Enums\OrderStatusEnum;
use App\Modules\Orders\Models\Order;
use App\Modules\Shippings\Enums\ShippingStatusEnum;
use App\Modules\Shippings\Models\Shipping;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CancelOrderTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(OrderStatusEnum $status): Order
    {
        // Sin eventos: el observer genera el PDF del ticket y no es parte de este test
        return Order::withoutEvents(function () use ($status) {
            $order = Order::create([
                'user_id' => User::factory()->create()->id,
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

    private function makeShipping(Order $order, ShippingStatusEnum $status): Shipping
    {
        $driver = Driver::create([
            'user_id' => User::factory()->create()->id,
            'type' => 'car',
            'license_plate' => 'ABC-123',
        ]);

        return Shipping::create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'status' => $status,
        ]);
    }

    public function test_admin_cancels_order_without_shippings(): void
    {
        $order = $this->makeOrder(OrderStatusEnum::Processing);

        Sanctum::actingAs($this->adminUser('manage.orders'));

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
    }

    public function test_order_with_pending_shipping_cannot_be_cancelled(): void
    {
        $order = $this->makeOrder(OrderStatusEnum::Shipped);
        $this->makeShipping($order, ShippingStatusEnum::Pending);

        Sanctum::actingAs($this->adminUser('manage.orders'));

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'No se puede cancelar la orden porque tiene envíos pendientes.');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'shipped']);
    }

    public function test_failed_order_cannot_be_cancelled_until_product_is_returned(): void
    {
        $order = $this->makeOrder(OrderStatusEnum::Failed);
        $this->makeShipping($order, ShippingStatusEnum::Failed);

        Sanctum::actingAs($this->adminUser('manage.orders'));

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'No se puede cancelar la orden porque el producto no se ha retornado a la tienda.');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'failed']);
    }

    public function test_refunded_order_with_finished_shippings_can_be_cancelled(): void
    {
        $order = $this->makeOrder(OrderStatusEnum::Refunded);
        $this->makeShipping($order, ShippingStatusEnum::Failed);

        Sanctum::actingAs($this->adminUser('manage.orders'));

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
    }

    public function test_cancelled_order_cannot_be_cancelled_again(): void
    {
        $order = $this->makeOrder(OrderStatusEnum::Cancelled);

        Sanctum::actingAs($this->adminUser('manage.orders'));

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_refunded_order_can_be_shipped_again(): void
    {
        $order = $this->makeOrder(OrderStatusEnum::Refunded);
        $driver = Driver::create([
            'user_id' => User::factory()->create()->id,
            'type' => 'car',
            'license_plate' => 'XYZ-789',
        ]);

        Sanctum::actingAs($this->adminUser('manage.orders', 'manage.shippings'));

        $this->postJson("/api/admin/orders/{$order->id}/shipping", ['driver_id' => $driver->id])
            ->assertCreated()
            ->assertJsonPath('status', 'pending');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'shipped']);
    }
}
