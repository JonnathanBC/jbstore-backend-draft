<?php

namespace Tests\Feature\Payments;

use App\Modules\Cart\Services\CartService;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GeneratePaymentSessionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.niubiz.url_api' => 'https://niubiz.test',
            'services.niubiz.user' => 'test-user',
            'services.niubiz.password' => 'test-password',
            'services.niubiz.merchant_id' => 'merchant-id',
        ]);

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    private function mockCartTotals(float $subtotal, float $total): void
    {
        $this->mock(CartService::class)
            ->shouldReceive('totalsFor')
            ->with($this->user->id)
            ->andReturn(['subtotal' => $subtotal, 'shipping' => 3.0, 'total' => $total]);
    }

    public function test_it_starts_a_pending_payment_with_a_server_generated_purchase_number(): void
    {
        $this->mockCartTotals(647.18, 650.18);

        Http::fake([
            'https://niubiz.test/api.security/v1/security' => Http::response('niubiz-token', 201),
            'https://niubiz.test/api.ecommerce/v2/ecommerce/token/session/merchant-id' => Http::response([
                'sessionKey' => 'niubiz-session-key',
            ], 201),
        ]);

        $response = $this->postJson('/api/payments/session')
            ->assertOk()
            ->assertJsonPath('sessionKey', 'niubiz-session-key')
            ->assertJsonPath('amount', 650.18);

        // Niubiz exige un número de compra numérico de hasta 12 dígitos
        $purchaseNumber = $response->json('purchaseNumber');
        $this->assertMatchesRegularExpression('/^\d{12}$/', $purchaseNumber);

        $this->assertDatabaseHas('payments', [
            'user_id' => $this->user->id,
            'purchase_number' => $purchaseNumber,
            'amount' => 650.18,
            'status' => 'pending',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/token/session/')
            && $request['amount'] === 650.18
        );
    }

    public function test_each_session_gets_a_different_purchase_number(): void
    {
        $this->mockCartTotals(10.0, 13.0);

        Http::fake([
            'https://niubiz.test/api.security/v1/security' => Http::response('niubiz-token', 201),
            'https://niubiz.test/*' => Http::response(['sessionKey' => 'key'], 201),
        ]);

        $first = $this->postJson('/api/payments/session')->json('purchaseNumber');
        $second = $this->postJson('/api/payments/session')->json('purchaseNumber');

        $this->assertNotSame($first, $second);
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_empty_cart_returns_422_and_creates_no_payment(): void
    {
        $this->mockCartTotals(0.0, 0.0);
        Http::fake();

        $this->postJson('/api/payments/session')->assertUnprocessable();

        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
    }

    public function test_it_returns_a_gateway_error_when_niubiz_session_request_fails(): void
    {
        $this->mockCartTotals(647.18, 650.18);

        Http::fake([
            'https://niubiz.test/api.security/v1/security' => Http::response('niubiz-token', 201),
            'https://niubiz.test/api.ecommerce/v2/ecommerce/token/session/merchant-id' => Http::response([
                'message' => 'Invalid session request',
            ], 400),
        ]);

        $this->postJson('/api/payments/session')
            ->assertStatus(502)
            ->assertJsonPath('message', 'No se pudo iniciar la sesión de pago');

        $this->assertDatabaseCount('payments', 0);
    }
}
