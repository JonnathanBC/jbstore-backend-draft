<?php

namespace App\Modules\Payments;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PaymentsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        Route::middleware('api')->group(__DIR__ . '/Routes/api.php');
    }
}
