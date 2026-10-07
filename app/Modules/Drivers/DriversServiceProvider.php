<?php

namespace App\Modules\Drivers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class DriversServiceProvider extends ServiceProvider
{
    public function register(): void
    {

    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        Route::middleware('api')->group(__DIR__ . '/Routes/admin.php');
    }
}
