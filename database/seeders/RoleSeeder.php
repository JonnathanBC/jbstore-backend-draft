<?php

namespace Database\Seeders;

use App\Modules\Users\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = Role::create([
            'name' => 'admin'
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
            'manage.shippings'
        ]);

        $user = User::find(1);
        $user->assignRole($admin);

        $driver = Role::create([
            'name' => 'driver'
        ]);

        $driver->syncPermissions([
            'access.dashboard',
            'manage.shippings',
        ]);
    }
}
