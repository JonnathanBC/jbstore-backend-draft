<?php

namespace App\Modules\Auth\Actions;

use App\Modules\Users\Models\User;
use Laravel\Socialite\Two\User as GoogleUser;

class FindOrCreateGoogleUser
{
    /** null = no se puede iniciar sesión (email ya registrado pero Google no lo verificó). */
    public function handle(GoogleUser $googleUser): ?User
    {
        $user = User::where('google_id', $googleUser->getId())->first();

        if ($user) {
            $user->update(['avatar' => $googleUser->getAvatar()]);

            return $user;
        }

        $existing = User::where('email', $googleUser->getEmail())->first();

        if ($existing) {
            // Vincular por email SOLO si Google lo verificó: si no, cualquiera tomaría una cuenta ajena
            if (! ($googleUser->user['email_verified'] ?? false)) {
                return null;
            }

            // Cuenta existente: no pisamos nombre/apellido que el usuario ya cargó
            $existing->update([
                'google_id' => $googleUser->getId(),
                'avatar' => $existing->avatar ?? $googleUser->getAvatar(),
            ]);

            return $existing;
        }

        // Google no da documento ni teléfono: el perfil se completa en el checkout
        return User::create([
            'google_id' => $googleUser->getId(),
            'name' => $googleUser->user['given_name'] ?? $googleUser->getName(),
            'last_name' => $googleUser->user['family_name'] ?? null,
            'email' => $googleUser->getEmail(),
            'avatar' => $googleUser->getAvatar(),
        ]);
    }
}
