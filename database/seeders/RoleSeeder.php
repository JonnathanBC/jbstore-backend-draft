<?php

namespace Database\Seeders;

use App\Modules\Users\Enums\UserRoleEnum;
use App\Modules\Users\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $admin->syncPermissions([
            'access.dashboard',
            'manage.options',
            'manage.families',
            'manage.categories',
            'manage.subcategories',
            'manage.products',
            'manage.covers',
            'manage.drivers',
            'manage.orders',
            'manage.shippings',
            'manage.users',
        ]);

        User::query()
            ->where('role', UserRoleEnum::Admin->value)
            ->each(fn (User $user) => $user->assignRole($admin));

        $driver = Role::firstOrCreate([
            'name' => 'driver',
            'guard_name' => 'web',
        ]);

        $driver->syncPermissions([
            'access.dashboard',
        ]);
    }
}
