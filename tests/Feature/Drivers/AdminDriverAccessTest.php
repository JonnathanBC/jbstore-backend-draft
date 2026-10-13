<?php

namespace Tests\Feature\Drivers;

use App\Modules\Users\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDriverAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_role_can_access_drivers_routes(): void
    {
        $admin = User::factory()->admin()->create();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/drivers')->assertOk();
    }
}
