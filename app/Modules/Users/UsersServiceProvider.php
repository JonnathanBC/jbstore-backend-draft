<?php

namespace App\Modules\Users;

use App\Modules\Users\Models\User;
use App\Modules\Users\Policies\UserPolicy;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class UsersServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        Route::prefix('api')
            ->middleware('api')
            ->group(__DIR__ . '/routes/api.php');

        Gate::policy(User::class, UserPolicy::class);
    }
}
