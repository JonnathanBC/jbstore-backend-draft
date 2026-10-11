<?php

namespace App\Modules\Users\Events;

use App\Modules\Users\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Otros módulos guardan copias de datos del usuario (ej. Addresses en receiver_info)
 * y las refrescan escuchando esto.
 */
class UserProfileUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $user,
    ) {}
}
