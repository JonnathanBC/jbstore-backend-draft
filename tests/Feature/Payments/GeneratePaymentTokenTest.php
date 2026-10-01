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
            ->assertCreated()
            ->assertJsonPath('accessToken', 'niubiz-token');
    }

    public function test_it_preserves_niubiz_authentication_errors_as_json(): void
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
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthorized access');
    }
}
