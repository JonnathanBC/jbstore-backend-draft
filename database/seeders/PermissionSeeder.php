<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
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
        ];

        foreach ($permissions as $permission) {
            Permission::create([
                'name' => $permission
            ]);


        }
    }
}
