<?php

namespace App\Modules\Orders;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Observers\OrderObserver;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class OrdersServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        $this->loadViewsFrom(
            app_path('Modules/Orders/Views'),
            'orders'
        );
        Route::middleware('api')->group(__DIR__ . '/Routes/api.php');
        Route::middleware('api')->group(__DIR__ . '/Routes/admin.php'); // verificar un middleware diff

        Order::observe(OrderObserver::class);
    }

}
