<?php

namespace Tests\Feature\Payments;

use App\Modules\Cart\Services\CartService;
use App\Modules\Orders\Actions\CreateOrderFromCart;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\Payment;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class CapturePaymentTest extends TestCase
{
    use RefreshDatabase;

    private const AUTHORIZE_URL = 'https://niubiz.test/api.authorization/v3/authorization/ecommerce/merchant-id';

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

    private function makePayment(array $attributes = []): Payment
    {
        return Payment::create([
            'user_id' => $this->user->id,
            'purchase_number' => '123456789012',
            'amount' => 650.18,
            'currency' => 'PEN',
            ...$attributes,
        ]);
    }

    private function mockCartTotal(float $total): void
    {
        $this->mock(CartService::class)
            ->shouldReceive('totalsFor')
            ->with($this->user->id)
            ->andReturn(['subtotal' => $total - 3, 'shipping' => 3.0, 'total' => $total]);
    }

    private function makeOrder(): Order
    {
        // Sin eventos: el observer genera el PDF del ticket y no es parte de este test
        return Order::withoutEvents(fn () => Order::create([
            'user_id' => $this->user->id,
            'payment_id' => 'TX-1',
            'content' => [],
            'address' => [],
            'total' => 650.18,
        ]));
    }

    private function expectOrderCreated(int $times = 1): Order
    {
        $order = $this->makeOrder();

        $this->mock(CreateOrderFromCart::class)
            ->shouldReceive('handle')
            ->times($times)
            ->with('TX-1', $this->user->id)
            ->andReturn($order);

        return $order;
    }

    private function fakeNiubizApproved(): void
    {
        Http::fake([
            'https://niubiz.test/api.security/v1/security' => Http::response('niubiz-token', 201),
            self::AUTHORIZE_URL => Http::response([
                'order' => [
                    'tokenId' => 'secret-transaction-token',
                    'purchaseNumber' => '123456789012',
                    'authorizedAmount' => 650.18,
                    'currency' => 'PEN',
                    'transactionDate' => '261009153000',
                ],
                'dataMap' => [
                    'ACTION_CODE' => '000',
                    'TRANSACTION_ID' => 'TX-1',
                    'CARD' => '455170******8059',
                    'BRAND' => 'visa',
                ],
            ]),
        ]);
    }

    private function fakeNiubizRejected(): void
    {
        Http::fake([
            'https://niubiz.test/api.security/v1/security' => Http::response('niubiz-token', 201),
            self::AUTHORIZE_URL => Http::response([
                'errorMessage' => 'Operacion Denegada.',
                'data' => [
                    'ACTION_CODE' => '191',
                    'ACTION_DESCRIPTION' => 'Operacion Denegada. Tarjeta vencida',
                    'CARD' => '455170******8059',
                    'BRAND' => 'visa',
                ],
            ], 400),
        ]);
    }

    private function capture(string $purchaseNumber = '123456789012')
    {
        return $this->postJson('/api/payments/capture', [
            'purchaseNumber' => $purchaseNumber,
            'transactionToken' => 'secret-transaction-token',
            'amount' => 1, // el cliente puede mandar cualquier cosa: se ignora
        ]);
    }

    private function authorizeCalls(): int
    {
        return Http::recorded(fn (Request $request) => $request->url() === self::AUTHORIZE_URL)->count();
    }

    public function test_approved_payment_creates_one_order_and_charges_the_server_amount(): void
    {
        $payment = $this->makePayment();
        $this->mockCartTotal(650.18);
        $order = $this->expectOrderCreated();
        $this->fakeNiubizApproved();

        $this->capture()
            ->assertOk()
            ->assertJsonPath('order.purchaseNumber', '123456789012')
            ->assertJsonPath('dataMap.CARD', '455170******8059');

        $payment->refresh();
        $this->assertSame('authorized', $payment->status->value);
        $this->assertSame('TX-1', $payment->provider_transaction_id);
        $this->assertSame($order->id, $payment->order_id);
        $this->assertSame('455170******8059', $payment->card_masked);

        $this->assertSame(1, $this->authorizeCalls());
        Http::assertSent(fn (Request $request) => $request->url() === self::AUTHORIZE_URL
            && $request['order']['amount'] === 650.18
            && $request['order']['purchaseNumber'] === '123456789012'
        );
    }

    public function test_the_transaction_token_is_never_stored(): void
    {
        $this->makePayment();
        $this->mockCartTotal(650.18);
        $this->expectOrderCreated();
        $this->fakeNiubizApproved();

        $this->capture()->assertOk();

        $this->assertStringNotContainsString(
            'secret-transaction-token',
            json_encode(Payment::first()->getAttributes()),
        );
    }

    public function test_capturing_the_same_purchase_twice_charges_once_and_returns_the_same_result(): void
    {
        $this->makePayment();
        $this->mockCartTotal(650.18);
        $this->expectOrderCreated(times: 1);
        $this->fakeNiubizApproved();

        $first = $this->capture()->assertOk()->json();
        $second = $this->capture()->assertOk()->json();

        $this->assertSame($first, $second);
        $this->assertSame(1, $this->authorizeCalls());
    }

    public function test_rejected_payment_creates_no_order_and_replays_the_rejection(): void
    {
        $payment = $this->makePayment();
        $this->mockCartTotal(650.18);
        $this->expectOrderCreated(times: 0);
        $this->fakeNiubizRejected();

        $this->capture()
            ->assertStatus(402)
            ->assertJsonPath('message', 'Operacion Denegada. Tarjeta vencida')
            ->assertJsonPath('data.CARD', '455170******8059');

        $this->capture()->assertStatus(402);

        $this->assertSame('rejected', $payment->refresh()->status->value);
        $this->assertNull($payment->order_id);
        $this->assertSame(1, $this->authorizeCalls());
    }

    public function test_another_users_purchase_number_returns_404(): void
    {
        $this->makePayment(['user_id' => User::factory()->create()->id]);
        $this->expectOrderCreated(times: 0);
        Http::fake();

        $this->capture()->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_cart_changed_since_session_returns_422_without_charging(): void
    {
        $payment = $this->makePayment(); // se inició por 650.18
        $this->mockCartTotal(700.00);    // el carrito cambió
        $this->expectOrderCreated(times: 0);
        Http::fake();

        $this->capture()
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cart');

        Http::assertNothingSent();
        $this->assertSame('pending', $payment->refresh()->status->value);
    }

    public function test_order_creation_failure_leaves_an_authorized_payment_without_order(): void
    {
        $payment = $this->makePayment();
        $this->mockCartTotal(650.18);
        $this->fakeNiubizApproved();

        $this->mock(CreateOrderFromCart::class)
            ->shouldReceive('handle')
            ->andThrow(new RuntimeException('DB caída'));

        $this->capture()->assertServerError();

        // Cobrado y detectable para reconciliar: nunca se pierde el registro
        $payment->refresh();
        $this->assertSame('authorized', $payment->status->value);
        $this->assertSame('TX-1', $payment->provider_transaction_id);
        $this->assertNull($payment->order_id);
    }

    public function test_gateway_failure_marks_the_payment_as_error_and_never_retries_the_charge(): void
    {
        $payment = $this->makePayment();
        $this->mockCartTotal(650.18);
        $this->expectOrderCreated(times: 0);

        Http::fake([
            'https://niubiz.test/api.security/v1/security' => Http::response('niubiz-token', 201),
            self::AUTHORIZE_URL => Http::response('Internal error', 500),
        ]);

        $this->capture()->assertStatus(502);
        $this->capture()->assertStatus(502);

        // No sabemos si cobró: no se vuelve a llamar a autorizar
        $this->assertSame('error', $payment->refresh()->status->value);
        $this->assertSame(1, $this->authorizeCalls());
    }

    public function test_purchase_number_is_required(): void
    {
        $this->postJson('/api/payments/capture', ['transactionToken' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('purchaseNumber');
    }
}
