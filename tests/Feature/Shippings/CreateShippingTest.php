<?php

namespace Tests\Feature\Shippings;

use App\Modules\Drivers\Models\Driver;
use App\Modules\Orders\Enums\OrderStatusEnum;
use App\Modules\Orders\Models\Order;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreateShippingTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(User $owner, OrderStatusEnum $status = OrderStatusEnum::Processing): Order
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

    private function makeDriver(): Driver
    {
        return Driver::create([
            'user_id' => User::factory()->create()->id,
            'type' => 'car',
            'license_plate' => 'ABC-123',
        ]);
    }

    public function test_admin_assigns_driver_and_order_moves_to_shipped(): void
    {
        $order = $this->makeOrder(User::factory()->create());
        $driver = $this->makeDriver();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson("/api/admin/orders/{$order->id}/shipping", ['driver_id' => $driver->id])
            ->assertCreated()
            ->assertJsonPath('order_id', $order->id)
            ->assertJsonPath('driver_id', $driver->id)
            ->assertJsonPath('status', 'pending');

        $this->assertDatabaseHas('shippings', ['order_id' => $order->id, 'driver_id' => $driver->id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'shipped']);
    }

    public function test_order_not_in_processing_returns_422_and_creates_nothing(): void
    {
        $order = $this->makeOrder(User::factory()->create(), OrderStatusEnum::Pending);
        $driver = $this->makeDriver();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson("/api/admin/orders/{$order->id}/shipping", ['driver_id' => $driver->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseCount('shippings', 0);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
    }

    public function test_unknown_driver_returns_422(): void
    {
        $order = $this->makeOrder(User::factory()->create());

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson("/api/admin/orders/{$order->id}/shipping", ['driver_id' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('driver_id');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'processing']);
    }

    public function test_missing_driver_returns_422(): void
    {
        $order = $this->makeOrder(User::factory()->create());

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson("/api/admin/orders/{$order->id}/shipping", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('driver_id');
    }

    public function test_non_admin_gets_403(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user);
        $driver = $this->makeDriver();

        Sanctum::actingAs($user);

        $this->postJson("/api/admin/orders/{$order->id}/shipping", ['driver_id' => $driver->id])
            ->assertForbidden();

        $this->assertDatabaseCount('shippings', 0);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $order = $this->makeOrder(User::factory()->create());

        $this->postJson("/api/admin/orders/{$order->id}/shipping", ['driver_id' => 1])
            ->assertUnauthorized();
    }
}
