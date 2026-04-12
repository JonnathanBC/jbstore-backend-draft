<?php

namespace App\Modules\Users\Policies;

use App\Modules\Users\Models\User;

class UserPolicy
{
    public function viewAny(User $authUser): bool
    {
        return true;
    }

    public function view(User $authUser, User $user): bool
    {
        $admin = $authUser->admin;

        if ($admin->id === $user->id) {
            return true;
        }

        return false;
    }

    public function create(User $authUser): bool
    {
        return true;
    }

    public function update(User $authUser, User $user): bool
    {
        return true;
    }

    public function delete(User $authUser, User $user): bool
    {
        return true;
    }
}
