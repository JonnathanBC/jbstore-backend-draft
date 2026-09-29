<?php

namespace App\Modules\Addresses\Factories;

use App\Modules\Addresses\Models\Address;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        $phone = fake()->numerify('09########');

        return [
            'user_id' => User::factory(),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => null,
            'city' => fake()->city(),
            'province' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'country' => 'EC',
            'reference' => null,
            'phone' => $phone,
            'receiver' => 1,
            'receiver_info' => ['name' => fake()->name(), 'phone' => $phone],
            'is_default' => false,
        ];
    }
}
