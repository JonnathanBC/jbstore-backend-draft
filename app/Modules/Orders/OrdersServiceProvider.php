<?php

namespace App\Modules\Orders;

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
    }

}
