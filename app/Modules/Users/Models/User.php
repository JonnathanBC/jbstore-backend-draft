<?php

namespace App\Modules\Users\Models;

use App\Modules\Addresses\Models\Address;
use App\Modules\Users\Enums\UserRoleEnum;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected static function newFactory()
    {
        return \App\Modules\Users\Factories\UserFactory::new();
    }

    protected $fillable = [
        'name',
        'last_name',
        'document_type',
        'document_number',
        'email',
        'phone',
        'password',
        'google_id',
        'avatar',
        'role'
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRoleEnum::class,
        ];
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }
}
