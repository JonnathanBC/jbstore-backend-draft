<?php

namespace Tests\Feature\Users;

use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUsersPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_users_with_pagination_and_creation_order(): void
    {
        $admin = $this->adminUser('manage.users');
        User::factory()->count(3)->create();

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users/list?page=2&per_page=2&order[created_at]=asc')
            ->assertOk()
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('per_page', 2)
            ->assertJsonPath('total', 4)
            ->assertJsonCount(2, 'data');
    }

    public function test_existing_users_endpoint_remains_an_autocomplete_list(): void
    {
        $admin = $this->adminUser('manage.users');
        User::factory()->count(2)->create();

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonCount(3);
    }
}
