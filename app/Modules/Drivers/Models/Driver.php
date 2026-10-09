<?php

namespace App\Modules\Drivers\Models;

use App\Modules\Drivers\Enums\DriveTypeEnum;
use App\Modules\Shippings\Models\Shipping;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Driver extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'license_plate',
    ];

    protected function casts(): array
    {
        return [
            'type' => DriveTypeEnum::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shippings()
    {
        return $this->hasMany(Shipping::class);
    }
}
