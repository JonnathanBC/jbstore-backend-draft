<?php

namespace Tests\Feature\Addresses;

use App\Modules\Addresses\Models\Address;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SetDefaultAddressTest extends TestCase
{
    use RefreshDatabase;

    private function makeAddress(User $user, bool $isDefault = false): Address
    {
        return Address::factory()->for($user)->create(['is_default' => $isDefault]);
    }

    private function validPayload(array $overrides = []): array
    {
        return [
            'address_line_1' => 'Av. Siempre Viva 123',
            'city' => 'Quito',
            'province' => 'Pichincha',
            'country' => 'EC',
            'phone' => '0991234567',
            ...$overrides,
        ];
    }

    public function test_marking_as_default_unsets_previous_default(): void
    {
        $user = User::factory()->create();
        $previous = $this->makeAddress($user, true);
        $new = $this->makeAddress($user);

        Sanctum::actingAs($user);

        $this->patchJson("/api/addresses/{$new->id}/default")
            ->assertOk()
            ->assertJsonPath('id', $new->id)
            ->assertJsonPath('is_default', true);

        $this->assertDatabaseHas('addresses', ['id' => $new->id, 'is_default' => true]);
        $this->assertDatabaseHas('addresses', ['id' => $previous->id, 'is_default' => false]);
    }

    public function test_marking_as_default_does_not_touch_other_users_addresses(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherDefault = $this->makeAddress($other, true);
        $mine = $this->makeAddress($user);

        Sanctum::actingAs($user);

        $this->patchJson("/api/addresses/{$mine->id}/default")->assertOk();

        $this->assertDatabaseHas('addresses', ['id' => $otherDefault->id, 'is_default' => true]);
    }

    public function test_patch_on_another_users_address_returns_404(): void
    {
        $user = User::factory()->create();
        $foreign = $this->makeAddress(User::factory()->create());

        Sanctum::actingAs($user);

        $this->patchJson("/api/addresses/{$foreign->id}/default")->assertNotFound();

        $this->assertDatabaseHas('addresses', ['id' => $foreign->id, 'is_default' => false]);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $address = $this->makeAddress(User::factory()->create());

        $this->patchJson("/api/addresses/{$address->id}/default")->assertUnauthorized();
    }

    public function test_store_with_is_default_unsets_previous_default(): void
    {
        $user = User::factory()->create();
        $previous = $this->makeAddress($user, true);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/addresses', $this->validPayload(['is_default' => true]))
            ->assertCreated();

        $this->assertDatabaseHas('addresses', ['id' => $response->json('id'), 'is_default' => true]);
        $this->assertDatabaseHas('addresses', ['id' => $previous->id, 'is_default' => false]);
    }

    public function test_first_address_becomes_default_automatically(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/addresses', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('is_default', true);

        $this->assertDatabaseHas('addresses', ['id' => $response->json('id'), 'is_default' => true]);
    }

    public function test_second_address_is_not_default_unless_requested(): void
    {
        $user = User::factory()->create();
        $first = $this->makeAddress($user, true);
        Sanctum::actingAs($user);

        $this->postJson('/api/addresses', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('is_default', false);

        $this->assertDatabaseHas('addresses', ['id' => $first->id, 'is_default' => true]);
    }
}
