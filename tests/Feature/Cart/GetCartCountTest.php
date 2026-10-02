<?php

namespace Tests\Feature\Cart;

use App\Modules\Cart\Services\CartService;
use App\Modules\Users\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetCartCountTest extends TestCase
{
    public function test_it_returns_the_authenticated_users_cart_count(): void
    {
        $user = new User;
        $user->id = 1;
        Sanctum::actingAs($user);

        $this->mock(CartService::class)
            ->shouldReceive('count')
            ->once()
            ->with($user->id)
            ->andReturn(4);

        $this->getJson('/api/cart/count')
            ->assertOk()
            ->assertExactJson(['count' => 4]);
    }
}
