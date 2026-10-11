<?php

namespace App\Modules\Auth\Actions;

use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class ExchangeGoogleLoginCode
{
    public function handle(string $code): array
    {
        // pull = leer y borrar: el código sirve una sola vez
        $userId = Cache::pull(IssueGoogleLoginCode::cacheKey($code));
        $user = $userId ? User::find($userId) : null;

        if (! $user) {
            throw ValidationException::withMessages([
                'code' => ['El código de inicio de sesión es inválido o expiró.'],
            ]);
        }

        return [
            'user' => $user,
            'token' => $user->createToken('auth-token')->plainTextToken,
        ];
    }
}
