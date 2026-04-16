<?php

namespace App\Modules\Users\Enums;

enum UserRoleEnum: string
{
    case Admin = 'ROLE_ADMIN';
    case User = 'ROLE_USER';
}
