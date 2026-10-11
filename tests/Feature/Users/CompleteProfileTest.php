<?php

namespace Tests\Feature\Users;

use App\Modules\Addresses\Models\Address;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompleteProfileTest extends TestCase
{
    use RefreshDatabase;

    private function makeGoogleUser(): User
    {
        // Así queda un usuario que entró por primera vez con Google
        return User::factory()->create([
            'google_id' => 'google-123',
            'password' => null,
            'document_type' => null,
            'document_number' => null,
            'phone' => null,
        ]);
    }

    private function validProfile(array $overrides = []): array
    {
        return [
            'last_name' => 'Pérez',
            'document_type' => 'CI',
            'document_number' => '1234567890',
            'phone' => '0991234567',
            ...$overrides,
        ];
    }

    public function test_me_reports_whether_profile_is_complete(): void
    {
        Sanctum::actingAs($this->makeGoogleUser());
        $this->getJson('/api/user/me')->assertOk()->assertJsonPath('profile_complete', false);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/user/me')->assertOk()->assertJsonPath('profile_complete', true);
    }

    public function test_user_completes_profile(): void
    {
        $user = $this->makeGoogleUser();
        Sanctum::actingAs($user);

        $this->patchJson('/api/user/me/profile', $this->validProfile())
            ->assertOk()
            ->assertJsonPath('profile_complete', true)
            ->assertJsonPath('document_number', '1234567890');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'last_name' => 'Pérez',
            'document_type' => 'CI',
            'document_number' => '1234567890',
            'phone' => '0991234567',
        ]);
    }

    public function test_document_must_match_its_type(): void
    {
        Sanctum::actingAs($this->makeGoogleUser());

        $this->patchJson('/api/user/me/profile', $this->validProfile(['document_number' => '123']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document_number');

        $this->patchJson('/api/user/me/profile', $this->validProfile(['document_type' => 'XX']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document_type');
    }

    public function test_phone_already_used_by_another_user_returns_422(): void
    {
        User::factory()->create(['phone' => '0991234567']);
        Sanctum::actingAs($this->makeGoogleUser());

        $this->patchJson('/api/user/me/profile', $this->validProfile())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_user_can_keep_own_phone(): void
    {
        $user = User::factory()->create(['phone' => '0991234567']);
        Sanctum::actingAs($user);

        $this->patchJson('/api/user/me/profile', $this->validProfile())->assertOk();
    }

    public function test_completing_profile_updates_document_in_existing_addresses(): void
    {
        $user = $this->makeGoogleUser();
        $address = Address::factory()->create([
            'user_id' => $user->id,
            'receiver_info' => ['name' => 'Ana', 'phone' => '0990000000', 'document_number' => null],
        ]);
        $otherAddress = Address::factory()->create([
            'receiver_info' => ['name' => 'Otro', 'phone' => '0990000001', 'document_number' => '9999999999'],
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/user/me/profile', $this->validProfile())->assertOk();

        $this->assertSame('1234567890', $address->fresh()->receiver_info['document_number']);
        $this->assertSame('Ana', $address->fresh()->receiver_info['name']);
        // Las direcciones de otros usuarios no se tocan
        $this->assertSame('9999999999', $otherAddress->fresh()->receiver_info['document_number']);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->patchJson('/api/user/me/profile', $this->validProfile())->assertUnauthorized();
    }
}
