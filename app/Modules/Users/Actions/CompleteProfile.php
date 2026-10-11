<?php

namespace App\Modules\Users\Actions;

use App\Modules\Users\Events\UserProfileUpdated;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;

class CompleteProfile
{
    public function handle(User $user, array $data): User
    {
        // Perfil y copias en otros módulos cambian juntos
        return DB::transaction(function () use ($user, $data) {
            $user->update($data);

            UserProfileUpdated::dispatch($user);

            return $user;
        });
    }
}
