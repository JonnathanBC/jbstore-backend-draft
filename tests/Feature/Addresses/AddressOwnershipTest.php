<?php

namespace Tests\Feature\Addresses;

use App\Concerns\Scopes\OwnedByUserScope;
use App\Modules\Addresses\Models\Address;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AddressOwnershipTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_user_only_lists_own_addresses(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $own = Address::factory()->for($userA)->create();
        $foreign = Address::factory()->for($userB)->create();

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/addresses')->assertOk();

        $ids = collect($response->json())->pluck('id');
        $this->assertTrue($ids->contains($own->id));
        $this->assertFalse($ids->contains($foreign->id));
    }

    public function test_store_assigns_authenticated_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/addresses', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('user_id', $user->id)
            ->assertJsonPath('receiver_info.document_number', $user->document_number);

        $this->assertDatabaseHas('addresses', ['user_id' => $user->id, 'city' => 'Quito']);
    }

    public function test_update_fills_document_number_for_an_existing_address(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create([
            'receiver_info' => ['name' => $user->name, 'phone' => '0991234567'],
        ]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/addresses/{$address->id}", ['city' => 'Guayaquil'])
            ->assertOk()
            ->assertJsonPath('receiver_info.document_number', $user->document_number);

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'city' => 'Guayaquil',
        ]);
    }

    public function test_store_ignores_user_id_sent_in_body(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/addresses', $this->validPayload(['user_id' => $other->id]))
            ->assertCreated()
            ->assertJsonPath('user_id', $user->id);

        $this->assertDatabaseMissing('addresses', ['user_id' => $other->id]);
    }

    public function test_scope_is_inactive_without_authenticated_user(): void
    {
        Address::factory()->for(User::factory())->count(2)->create();
        Address::factory()->for(User::factory())->create();

        $this->assertCount(3, Address::query()->get());
    }

    public function test_without_global_scope_bypasses_ownership_when_authenticated(): void
    {
        $userA = User::factory()->create();
        Address::factory()->for($userA)->create();
        Address::factory()->for(User::factory())->create();

        Sanctum::actingAs($userA);

        $this->assertCount(1, Address::query()->get());
        $this->assertCount(2, Address::query()->withoutGlobalScope(OwnedByUserScope::class)->get());
    }
}
