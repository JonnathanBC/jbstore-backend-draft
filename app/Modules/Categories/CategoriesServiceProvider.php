<?php

namespace App\Modules\Categories;

use App\Modules\Categories\Models\Category;
use App\Modules\Categories\Models\Family;
use App\Modules\Categories\Models\Subcategory;
use App\Modules\Categories\Services\CategoriesService;
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
    }
}
