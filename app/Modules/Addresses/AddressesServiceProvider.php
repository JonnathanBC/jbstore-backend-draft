<?php

namespace App\Modules\Addresses;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AddressesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        Event::listen(
            \App\Modules\Users\Events\UserProfileUpdated::class,
            \App\Modules\Addresses\Listeners\SyncReceiverInfoWithUserProfileListener::class
        );

        Route::middleware('api')->group(__DIR__ . '/Routes/api.php');
    }
}
