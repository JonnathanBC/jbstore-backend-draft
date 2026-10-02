<?php

namespace App\Modules\Orders\Models;

use App\Concerns\BelongsToUser;
use App\Modules\Orders\Enums\OrderStatusEnum;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use BelongsToUser;

    protected $fillable = [
        '',
    ];

    protected $guarded = [
        'status',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'address' => 'array',
            'status' => OrderStatusEnum::class,
        ];
    }
}
