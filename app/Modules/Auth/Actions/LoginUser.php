<?php

namespace App\Modules\Auth\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginUser
{
    public function handle(array $credentials): array
    {
        if (!Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = Auth::user();
        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
