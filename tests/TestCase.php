<?php

namespace Tests;

use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Permission;

abstract class TestCase extends BaseTestCase
{
    protected function adminUser(string ...$permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user = User::factory()->admin()->create();
        $user->givePermissionTo(...$permissions);

        return $user;
    }
}
