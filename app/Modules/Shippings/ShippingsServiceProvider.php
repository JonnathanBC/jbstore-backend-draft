<?php

namespace App\Modules\Shippings;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ShippingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        Route::middleware('api')->group(__DIR__ . '/Routes/admin.php');
    }

}
