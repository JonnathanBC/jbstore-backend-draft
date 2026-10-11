<?php

namespace Tests\Feature\Auth;

use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Two\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    private const STATE = 'aB3dE5fG7hJ9kL1mN3pQ5rS7tU9vW1xY3zA5bC7dE9f';

    private function mockGoogleUser(bool $emailVerified = true): void
    {
        // Google manda nombre y apellido por separado en el payload crudo
        $googleUser = (new SocialiteUser)
            ->setRaw([
                'given_name' => 'Ana',
                'family_name' => 'Pérez',
                'email_verified' => $emailVerified,
            ])
            ->map([
                'id' => 'google-123',
                'name' => 'Ana Pérez',
                'email' => 'ana@example.com',
                'avatar' => 'https://example.com/a.png',
            ]);

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    /** Hace el callback de Google y devuelve el código de un solo uso que viaja al front. */
    private function issueCode(bool $emailVerified = true): string
    {
        $this->mockGoogleUser($emailVerified);

        $location = $this->get('/api/auth/google/callback?code=google-code&state='.self::STATE)
            ->assertRedirect()
            ->headers->get('Location');

        parse_str(parse_url($location, PHP_URL_QUERY), $query);

        return $query['code'];
    }

    public function test_redirect_forwards_state_to_google(): void
    {
        config(['services.google.client_id' => 'client-id']);

        $url = $this->getJson('/api/auth/google?state='.self::STATE)
            ->assertOk()
            ->json('url');

        $this->assertStringContainsString('state='.self::STATE, $url);
    }

    public function test_redirect_without_valid_state_returns_422(): void
    {
        $this->getJson('/api/auth/google')->assertUnprocessable()->assertJsonValidationErrors('state');
        $this->getJson('/api/auth/google?state=short')->assertUnprocessable();
    }

    public function test_callback_redirects_with_one_time_code_and_never_the_token(): void
    {
        config(['app.frontend_url' => 'http://front.test']);
        $this->mockGoogleUser();

        $location = $this->get('/api/auth/google/callback?code=google-code&state='.self::STATE)
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringStartsWith('http://front.test/api/auth/google/callback?', $location);
        $this->assertStringContainsString('state='.self::STATE, $location);
        $this->assertStringNotContainsString('token=', $location);
        // El token se crea recién en el canje, no en el callback
        $this->assertDatabaseCount('personal_access_tokens', 0);
        // Google no da documento ni teléfono: el perfil queda incompleto hasta el checkout
        $this->assertDatabaseHas('users', [
            'google_id' => 'google-123',
            'email' => 'ana@example.com',
            'name' => 'Ana',
            'last_name' => 'Pérez',
            'document_number' => null,
            'phone' => null,
        ]);
    }

    public function test_google_login_links_existing_account_with_verified_email(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com', 'last_name' => 'Original']);

        $this->issueCode(emailVerified: true);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'google_id' => 'google-123',
            'last_name' => 'Original',
        ]);
    }

    public function test_google_login_does_not_link_account_with_unverified_email(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);
        $this->mockGoogleUser(emailVerified: false);

        $this->get('/api/auth/google/callback?code=google-code&state='.self::STATE)
            ->assertRedirect(config('app.frontend_url').'/login?error=google');

        $this->assertDatabaseMissing('users', ['google_id' => 'google-123']);
    }

    public function test_returning_google_user_keeps_completed_profile(): void
    {
        $user = User::factory()->create(['google_id' => 'google-123']);

        $this->issueCode();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'document_number' => $user->document_number,
            'phone' => $user->phone,
        ]);
    }

    public function test_exchange_returns_token_for_valid_code(): void
    {
        $code = $this->issueCode();

        $this->postJson('/api/auth/google/exchange', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('user.email', 'ana@example.com')
            ->assertJsonStructure(['user', 'token']);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_code_can_only_be_exchanged_once(): void
    {
        $code = $this->issueCode();

        $this->postJson('/api/auth/google/exchange', ['code' => $code])->assertOk();

        $this->postJson('/api/auth/google/exchange', ['code' => $code])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_expired_code_is_rejected(): void
    {
        $code = $this->issueCode();

        $this->travel(61)->seconds();

        $this->postJson('/api/auth/google/exchange', ['code' => $code])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_unknown_code_is_rejected(): void
    {
        User::factory()->create();

        $this->postJson('/api/auth/google/exchange', ['code' => str_repeat('x', 64)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->postJson('/api/auth/google/exchange', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }
}
