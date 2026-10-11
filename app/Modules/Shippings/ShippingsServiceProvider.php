<?php

namespace App\Modules\Shippings;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ShippingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        Event::listen(
            \App\Modules\Orders\Events\OrderStatusChanged::class,
            \App\Modules\Shippings\Listeners\MarkShippingAsRefundedListener::class
        );
        Route::middleware('api')->group(__DIR__ . '/Routes/admin.php');
    }

}
