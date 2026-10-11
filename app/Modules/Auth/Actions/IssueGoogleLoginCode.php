<?php

namespace App\Modules\Auth\Actions;

use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * El token NUNCA viaja en la URL (queda en historial, logs y Referer).
 * Al front le mandamos un código de un solo uso y vida corta que canjea server-to-server.
 */
class IssueGoogleLoginCode
{
    public const TTL_SECONDS = 60;

    public function handle(User $user): string
    {
        $code = Str::random(64);

        // Guardamos el hash: si alguien lee el cache, no obtiene códigos canjeables
        Cache::put(self::cacheKey($code), $user->id, self::TTL_SECONDS);

        return $code;
    }

    public static function cacheKey(string $code): string
    {
        return 'google-login:'.hash('sha256', $code);
    }
}
