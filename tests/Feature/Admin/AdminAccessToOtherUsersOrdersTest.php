<?php

namespace Tests\Feature\Admin;

use App\Modules\Drivers\Models\Driver;
use App\Modules\Orders\Enums\OrderStatusEnum;
use App\Modules\Orders\Models\Order;
use App\Modules\Shippings\Models\Shipping;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regresión: OwnedByUserScope filtra por Auth::id() también cuando el que opera es el admin,
 * así que las órdenes de los clientes "desaparecían" en el panel.
 */
class AdminAccessToOtherUsersOrdersTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());
    }

    private function makeCustomerOrder(OrderStatusEnum $status): Order
    {
        // Sin eventos: el observer genera el PDF del ticket y no es parte de este test
        return Order::withoutEvents(function () use ($status) {
            $order = Order::create([
                'user_id' => $this->customer->id,
                'payment_id' => 'pay_'.fake()->unique()->numerify('####'),
                'content' => [],
                // Lo que el ticket imprime: la foto de la dirección al comprar
                'address' => [
                    'address_line_1' => 'Av. Siempre Viva 742',
                    'city' => 'Cuenca',
                    'reference' => null,
                    'receiver_info' => [
                        'name' => 'Ana Pérez',
                        'phone' => '0991234567',
                        'document_number' => '1234567890',
                    ],
                ],
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

        return Shipping::create(['order_id' => $order->id, 'driver_id' => $driver->id]);
    }

    public function test_admin_completes_shipping_of_another_users_order(): void
    {
        $this->freezeSecond();
        $order = $this->makeCustomerOrder(OrderStatusEnum::Shipped);
        $shipping = $this->makeShipping($order);

        $this->patchJson("/api/admin/shippings/{$shipping->id}/status", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('status', 'completed');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'completed']);
        $this->assertTrue($shipping->fresh()->delivered_at->equalTo(now()));
    }

    public function test_admin_marks_shipping_of_another_users_order_as_failed(): void
    {
        $order = $this->makeCustomerOrder(OrderStatusEnum::Shipped);
        $shipping = $this->makeShipping($order);

        $this->patchJson("/api/admin/shippings/{$shipping->id}/status", ['status' => 'failed'])
            ->assertOk();

        $this->assertDatabaseHas('shippings', ['id' => $shipping->id, 'status' => 'failed']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'failed']);
    }

    public function test_invalid_shipping_transition_returns_422(): void
    {
        $order = $this->makeCustomerOrder(OrderStatusEnum::Shipped);
        $shipping = $this->makeShipping($order);
        $shipping->update(['status' => 'completed']);

        $this->patchJson("/api/admin/shippings/{$shipping->id}/status", ['status' => 'failed'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_admin_orders_index_lists_orders_of_every_user(): void
    {
        $order = $this->makeCustomerOrder(OrderStatusEnum::Pending);

        $this->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $order->id);
    }

    public function test_admin_downloads_ticket_of_another_users_order(): void
    {
        $order = $this->makeCustomerOrder(OrderStatusEnum::Pending);

        $this->get("/api/admin/orders/{$order->id}/ticket/download")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
