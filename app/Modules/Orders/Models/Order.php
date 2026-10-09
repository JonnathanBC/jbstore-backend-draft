<?php

namespace App\Modules\Orders\Models;

use App\Concerns\BelongsToUser;
use App\Modules\Orders\Enums\OrderStatusEnum;
use App\Modules\Shippings\Models\Shipping;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use BelongsToUser ,HasUuids;

    protected $fillable = [
        'user_id',
        'content',
        'address',
        'payment_id',
        'total',
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'address' => 'array',
            'status' => OrderStatusEnum::class,
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shippings()
    {
        return $this->hasMany(Shipping::class);
    }
}
