<?php

namespace App\Modules\Categories;

use App\Modules\Categories\Services\CategoriesService;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CategoriesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CategoriesService::class, function () {
            return new CategoriesService();
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        Route::middleware('api')->group(__DIR__ . '/routes/admin.php');
    }
}
