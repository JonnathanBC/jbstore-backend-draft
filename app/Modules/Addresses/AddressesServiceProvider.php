<?php

namespace App\Modules\Addresses;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AddressesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        Route::middleware('api')->group(__DIR__ . '/Routes/api.php');
    }
}
