<?php

namespace Tests\Feature\Payments;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeneratePaymentTokenTest extends TestCase
{
    public function test_it_returns_the_payment_token_as_json(): void
    {
        config([
            'services.niubiz.url_api' => 'https://niubiz.test',
            'services.niubiz.user' => 'test-user',
            'services.niubiz.password' => 'test-password',
        ]);

        Http::fake([
            'https://niubiz.test/api.security/v1/security' => Http::response('niubiz-token', 201),
        ]);

        $this->withoutMiddleware();
        $this->getJson('/api/payments/token')
            ->assertOk()
            ->assertJsonPath('accessToken', 'niubiz-token');
    }

    public function test_niubiz_error_is_hidden_behind_a_502(): void
    {
        config([
            'services.niubiz.url_api' => 'https://niubiz.test',
            'services.niubiz.user' => 'test-user',
            'services.niubiz.password' => 'test-password',
        ]);

        Http::fake([
            'https://niubiz.test/api.security/v1/security' => Http::response('Unauthorized access', 401),
        ]);

        $this->withoutMiddleware();
        $this->getJson('/api/payments/token')
            // No se filtra el status ni el mensaje de Niubiz: PaymentGatewayException responde 502 genérico
            ->assertStatus(502)
            ->assertJsonPath('message', 'No se pudo generar el token de pago');
    }
}
