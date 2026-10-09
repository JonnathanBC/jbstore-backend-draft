<?php

namespace Tests\Feature\Auth;

use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_and_returns_token(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Ana',
            'last_name' => 'Pérez',
            'document_type' => 'CI',
            'document_number' => '1234567890',
            'phone' => '0991234567',
            'email' => 'ana@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])
            ->assertCreated()
            ->assertJsonPath('user.email', 'ana@example.com')
            ->assertJsonStructure(['user', 'token']);

        $this->assertDatabaseHas('users', ['email' => 'ana@example.com']);
    }

    public function test_login_with_valid_credentials_returns_token(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['user', 'token']);
    }

    public function test_login_with_wrong_password_returns_422(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }
}
