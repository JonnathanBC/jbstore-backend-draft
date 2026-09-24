<?php

namespace App\Modules\Auth\Actions;

use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\Hash;

class RegisterAction
{
    public function execute(array $data): array
    {
        $user = User::create([
            'name'            => $data['name'],
            'last_name'       => $data['last_name'],
            'document_type'   => $data['document_type'],
            'document_number' => $data['document_number'],
            'phone'           => $data['phone'],
            'email'           => $data['email'],
            'password'        => Hash::make($data['password']),
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user'  => $user,
            'token' => $token,
        ];
    }
}
