<?php

namespace App\Modules\Users\Factories;

use App\Modules\Users\Enums\UserRoleEnum;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'document_type' => 'CI',
            'document_number' => fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('09########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRoleEnum::User,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Usuario con rol de administrador.
     */
    public function admin(): static
    {
        return $this->state(fn(array $attributes) => [
            'role' => UserRoleEnum::Admin,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
