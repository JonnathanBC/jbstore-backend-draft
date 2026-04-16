<?php

namespace App\Policies;

use App\Modules\Users\Models\User;

class AdminPolicy
{
    public function admin(User $user): bool
    {
        return $user->role === 'ROLE_ADMIN';
    }
}