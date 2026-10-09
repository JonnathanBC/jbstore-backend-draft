<?php

namespace App\Modules\Shippings\Models;

use App\Modules\Drivers\Models\Driver;
use App\Modules\Orders\Models\Order;
use App\Modules\Shippings\Enums\ShippingStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipping extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'driver_id',
        'status',
        'refunded_at',
        'delivered_at',
    ];

    protected $casts = [
        'status' => ShippingStatusEnum::class,
        'refunded_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}
