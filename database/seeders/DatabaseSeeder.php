<?php

namespace Database\Seeders;

use App\Modules\Users\Enums\UserRoleEnum;
use App\Modules\Users\Models\User;
use App\Modules\Products\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        Storage::deleteDirectory('products');
        Storage::makeDirectory('products');

        User::factory()->create([
            'name' => 'Jonnathan',
            'last_name' => 'Baculima Cuesta',
            'document_type' => 'CI',
            'document_number' => '1234567890',
            'email' => 'test@example.com',
            'phone' => '0987654321',
            'role' => UserRoleEnum::Admin,
        ]);

        $this->call([
            FamilySeeder::class,
            OptionSeeder::class,
        ]);

        Product::factory(600)->create();
    }
}
