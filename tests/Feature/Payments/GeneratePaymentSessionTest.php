<?php

namespace Tests\Feature\Payments;

use App\Modules\Cart\Services\CartService;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GeneratePaymentSessionTest extends TestCase
{
    public function test_it_returns_the_session_key_from_niubiz(): void
    {
        config([
            'services.niubiz.url_api' => 'https://niubiz.test',
            'services.niubiz.user' => 'test-user',
            'services.niubiz.password' => 'test-password',
            'services.niubiz.merchant_id' => 'merchant-id',
        ]);

        $user = new User;
        $user->id = 1;
        Sanctum::actingAs($user);
        $this->mock(CartService::class)
            ->shouldReceive('totalsFor')
            ->once()
            ->with($user->id)
            ->andReturn([
                'subtotal' => 647.18,
                'shipping' => 3.0,
                'total' => 650.18,
            ]);

        Http::fake([
            'https://niubiz.test/api.security/v1/security' => Http::response('niubiz-token', 201),
            'https://niubiz.test/api.ecommerce/v2/ecommerce/token/session/merchant-id' => Http::response([
                'sessionKey' => 'niubiz-session-key',
            ], 201),
        ]);

        $this->postJson('/api/payments/session')
            ->assertOk()
            ->assertJsonPath('sessionKey', 'niubiz-session-key')
            ->assertJsonPath('amount', 650.18);

        Http::assertSent(fn ($request) =>
            str_contains($request->url(), '/api.ecommerce/v2/ecommerce/token/session/')
            && $request['amount'] === 650.18
        );
    }

    public function test_it_returns_a_gateway_error_when_niubiz_session_request_fails(): void
    {
        config([
            'services.niubiz.url_api' => 'https://niubiz.test',
            'services.niubiz.user' => 'test-user',
            'services.niubiz.password' => 'test-password',
            'services.niubiz.merchant_id' => 'merchant-id',
        ]);

        $user = new User;
        $user->id = 1;
        Sanctum::actingAs($user);
        $this->mock(CartService::class)
            ->shouldReceive('totalsFor')
            ->once()
            ->with($user->id)
            ->andReturn([
                'subtotal' => 647.18,
                'shipping' => 3.0,
                'total' => 650.18,
            ]);

        Http::fake([
            'https://niubiz.test/api.security/v1/security' => Http::response('niubiz-token', 201),
            'https://niubiz.test/api.ecommerce/v2/ecommerce/token/session/merchant-id' => Http::response([
                'message' => 'Invalid session request',
            ], 400),
        ]);

        $this->postJson('/api/payments/session')
            ->assertStatus(502)
            ->assertJsonPath('message', 'No se pudo iniciar la sesión de pago');
    }
}
