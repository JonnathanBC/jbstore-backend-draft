<?php

namespace Tests\Feature\Orders;

use App\Modules\Orders\Enums\OrderStatusEnum;
use App\Modules\Orders\Models\Order;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UpdateOrderStatusTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(User $owner, OrderStatusEnum $status = OrderStatusEnum::Pending): Order
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

    public function test_admin_moves_pending_order_to_processing(): void
    {
        $order = $this->makeOrder(User::factory()->create());

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'processing'])
            ->assertOk()
            ->assertJsonPath('id', $order->id)
            ->assertJsonPath('status', 'processing');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'processing']);
    }

    public function test_invalid_transition_returns_422(): void
    {
        $order = $this->makeOrder(User::factory()->create(), OrderStatusEnum::Completed);

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'processing'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'completed']);
    }

    public function test_unknown_status_returns_422(): void
    {
        $order = $this->makeOrder(User::factory()->create());

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'teleported'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_non_admin_gets_403(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user);

        Sanctum::actingAs($user);

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'processing'])
            ->assertForbidden();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $order = $this->makeOrder(User::factory()->create());

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'processing'])
            ->assertUnauthorized();
    }
}
